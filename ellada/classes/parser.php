<?php 
include_once __DIR__."/database.php";

class Parser extends Helper {
   
    #########################
    ### Настройки фильтра ###
    #########################
    public $amount;
    public $period;
    public $region; 
    #########################
    ### Внутренние данные ###
    #########################
    private $sql;
    private $db;
    private $content =array();
    private $collect_arr = array();
	private $propositionKey = '1984994';
	private $sitename = 'https://www.ellada-porte.market';
    
    
    
    public function __construct() {         
        $this->db = new Database();
        $this->sql = $this->db->sql;        
    }    
    
    
    public function parse_search($page_num){
		$settings = array(  'url' => "https://www.ellada-porte.market/index.php?subcats=Y&pcode_from_q=Y&pshort=Y&pfull=Y&pname=Y&pkeywords=Y&search_performed=Y&hint_q=%D0%98%D1%81%D0%BA%D0%B0%D1%82%D1%8C+%D1%82%D0%BE%D0%B2%D0%B0%D1%80%D1%8B&dispatch=products.search&items_per_page=96&result_ids=pagination_contents&is_ajax=1&page={$page_num}");
		$html = $this->request($settings);
		$json = json_decode($html, true);
		
		if(!isset($json['html'])) {
			$this->printPre('{"status" : "search_end", "message":" Поиск завершен."}', true); 
		}
		$htmlReply = $json['html']['pagination_contents'];
		if(!preg_match('#class="ty-column3">#i', $htmlReply)) {
			$this->printPre('{"status" : "search_end", "message":" Поиск завершен."}', true); 
		}
		$content = [];
		$block = $this->_getFromPage('class="ty-column3">', '</form>', $htmlReply, 'array'); 
		for($i = 0; $i < count($block); $i++) {
			$block[$i] = preg_replace(array('#[\s]+<#i', '#>[\s+]#i'), array( '<', '>'), $block[$i]);
			$link = $this->_getFromPage('<bdi><a href="', '"', $block[$i], 'string');
			$linkIn = $this->sql->query("SELECT * FROM `{$this->db->table}` WHERE `link`='{$link}'");
			if(!$linkIn->num_rows) {
				$title = $this->prepareQuery($title);
				$this->sql->query("INSERT INTO `{$this->db->table}`(`link`) VALUES('{$link}')");
				if($this->sql->error) {
					return '{"status" : "error", "message":" Ошибка базы данных: '.__LINE__.' - '.$this->sql->error.'"}';
					//$this->printPre('{"status" : "error", "message":" Ошибка базы данных: '.__LINE__.' - '.$this->sql->error.'"}', true);
					//printf(__LINE__.": Сообщение ошибки: %s<br>\n",  $this->sql->error);
					//die();
				}
			}			
		}
		$page_num++;
		$this->printPre('{"status" : "nextPage", "message":"Следующая страница '.$page_num.'."}', true); 
    }
    
    
     
    
     
	public function parse_product($product_num) {
		//$zaymIn = $this->sql->query("SELECT * FROM `{$this->db->table}` WHERE `status`='0'");
		$zaymIn = $this->sql->query("SELECT * FROM `{$this->db->table}` LIMIT {$product_num},1");
		if(!$zaymIn->num_rows) {
			$this->printPre('{"status" : "end", "message":" Все займы отпарсены."}', true);
		}
		$ellada = $zaymIn->fetch_assoc();

		$content = array();

		//Запрос 
		$settings['url'] = $ellada['link'];
		$htmlReply = $this->request($settings);
		
		if(preg_match('#<title>Страница не найдена</title>#i', $htmlReply)) {
			$this->sql->query("DELETE FROM `{$this->db->table}` WHERE `link`='{$ellada['link']}'");
			$this->printPre('{"status" : "next", "message":" Товар '.$product_num.' удален"}', true);
		}

		$dom = new simple_html_dom(null, true, true, 'UTF-8', true, "\r\n", ' ');
		$dom2 = new simple_html_dom(null, true, true, 'UTF-8', true, "\r\n", ' ');
		$html =$dom->load($htmlReply, true, true);
		$product_block = $html->find('div.ty-product-block', 0);
		$html2 = $dom2->load($product_block, true, true);
		
		
		

		$products = array();
		
		$content = array_merge($content, $ellada);
		
		
		
		$product_name = trim(strip_tags($this->_getFromPage('<h1 class="ty-product-block-title" >', '</h1>', $html2, 'string')));	
		///////////////////
		//	ОБЩИЕ ДАННЫЕ //		
		///////////////////
		$products['total']['link'] = $ellada['link'];
		$products['total']['product_id'] = $this->_getFromPage('product_data[', ']', $html2, 'string');
		$products['total']['category'] = $this->_getFromPage('class="ty-breadcrumbs__a">', '</a>', $htmlReply, 'array');
		array_shift($products['total']['category']);
		$products['total']['brand'] = trim(strip_tags($this->_getFromPage('Бренд:', '</div>', $htmlReply, 'string')));//;
		
		
		//ТАБЫ
		$href = [];
		foreach($html -> find('li.ty-tabs__item') as $li) {
			if(preg_match('#tags|discussion|faq#i', $li)) {
				continue;
			}
			$id  = $this->_getFromPage('id="', '"', $li, 'string');
			//$content['tab'][$id]['name'] = strip_tags($li);
			$tab_content  = (string)$html2->find('div.ty-product-block div.cm-tabs-content div.content-'.$id, 0);
			switch($id) {
				case "description": {
					$products['total']['description'] = strip_tags($tab_content, "<ul><li><div><h4>");
					}; break;
				case "features": {
						$tab_content = preg_replace('#<div class="hidden ty-wysiwyg-content"(.*?)</div>#i', '', $tab_content);
						$feature = $this->_getFromPage('<div class="ty-product-feature">', '</div>', $tab_content, 'array');
						if(is_array($feature)) {
							$num = 0;
							for($i = 0; $i < count($feature); $i++) {
								if(preg_match('#Цвет:#i', $feature[$i])) {
									continue;
								}
								$products['total']['features'][$num]['name'] = trim(strip_tags($this->_getFromPage('<span class="ty-product-feature__label">', '</span>', $feature[$i], 'string')));;
								$products['total']['features'][$num]['value'] =trim(strip_tags("<li ".$this->_getFromPage('<li class="ty-product-feature__multiple-item"', '</li>', $feature[$i], 'string')));
								$num++;
							}
						}
					}; break;
				case "product_tab_9" :
				case "product_tab_10" :
				case "molding_products" : {
						$href2 = $this->_getFromPage('href="', '"', $tab_content, 'array');
						$href = array_merge($href, $href2);
					}; break;
				default: {$products['total']['tab'][$id]['value'] = $tab_content; }
			}
		}
		if(count($href) > 0) {
			$href = array_values(array_unique($href));
			for($i = 0; $i < count($href);$i++) {
				$linkIn = $this->sql->query("SELECT * FROM `{$this->db->table}` WHERE `link`='{$href[$i]}'");
				if(!$linkIn->num_rows) {
					$this->sql->query("INSERT INTO `{$this->db->table}`(`link`) VALUES('{$href[$i]}')");
					if($this->sql->error) {
						$this->printPre('{"status" : "error", "message":" Ошибка базы данных: '.__LINE__.' - '.$this->sql->error.'"}', true);
						//printf(__LINE__.": Сообщение ошибки: %s<br>\n",  $this->sql->error);
						//die();
					}
				}
				
			}
		}
		
		
		
		
		
		////////////////////////
		// Специфичные данные //
		////////////////////////
		$num = 0;
		if(preg_match('#ty-product-variant-image#i', $product_block)) {
			foreach($html -> find('div.ty-product-variant-image img') as $images_) {
				$fn_set_option_value = preg_replace(array('#[\s]+#i', '#&\#039;#i'), '', $this->_getFromPage('fn_set_option_value(', ')', $images_, 'string')); ;
				list($product_id, $post_id, $post_value_id ) = explode(',', $fn_set_option_value);						
				if($num < '1') {
					$settings['url'] = "https://www.ellada-porte.market/index.php?dispatch=products.options&changed_option[{$product_id}]={$post_id}&appearance[show_price_values]=1&appearance[show_price]=1&appearance[show_add_to_cart]=1&appearance[show_list_buttons]=1&appearance[but_role]=big&appearance[quick_view]=&ab__qobp[product_amount]=100&ab__qobp_data[phone]=&appearance[show_price_values]=1&appearance[show_list_discount]=1&appearance[show_product_options]=1&appearance[details_page]=1&additional_info[info_type]=D&additional_info[get_icon]=1&additional_info[get_detailed]=1&additional_info[get_additional]=&additional_info[get_options]=1&additional_info[get_discounts]=1&additional_info[get_features]=&additional_info[get_extra]=&additional_info[get_taxed_prices]=1&additional_info[get_for_one_product]=1&additional_info[detailed_params]=1&additional_info[features_display_on]=C&product_data[{$product_id}][product_options][{$post_id}]={$post_value_id}&appearance[show_sku]=1&appearance[show_qty]=1&appearance[capture_options_vs_qty]=&product_data[{$product_id}][amount]=1";
					$settings['post'] = "security_hash=&result_ids=product_images_{$product_id}_update%2Cold_price_update_{$product_id}%2Cprice_update_{$product_id}%2Cadd_to_cart_update_{$product_id}%2Cline_discount_update_{$product_id}%2Cproduct_options_update_{$product_id}%2Cadvanced_options_update_{$product_id}%2Csku_update_{$product_id}%2Cqty_update_{$product_id}&is_ajax=1";
					$htmlOption = $this->request($settings);
					$optionJson = json_decode($htmlOption, true);

					$image = $this->_getFromPage('href="https://www.ellada-porte.market/images/', '"', $optionJson['html']["product_images_{$product_id}_update"], 'string');
					$price = $this->_getFromPage('class="ty-price-num">', '.00', $optionJson['html']["price_update_{$product_id}"], 'string');
					$price = str_replace('&nbsp;', '', $price);
					$products['specific'][$num]['id'] = $product_id.$post_id.$post_value_id;
					$products['specific'][$num]['price'] = $price;
					$products['specific'][$num]['title'] = "{$product_name} - {$images_->alt}";
					$products['specific'][$num]['mini_image'] = $images_->src;
					$products['specific'][$num]['main_image'] = "https://www.ellada-porte.market/images/".$image;
					
					$product_options_update = $optionJson['html']["product_options_update_{$product_id}"];
					$products['specific'][$num]['select'] =  $this->_getSelect($product_options_update, $product_id, $post_id);
					$products['specific'][$num]['title'] = "{$product_name} - {$this->content[$post_id][$post_value_id]}";
				}
				$num++;
			}
		}
		else {
			
		}
		$this->printPre($products, true); 
		
		
		/**/
		
		
		//$content['features'] = isset($content['features']) ? serialize($content['features']) : serialize(array());
		//$content['select'] = serialize($content['select']);
		
		echo "<pre>";
		$this->printPre($content);
		echo "<pre>";
		die();
		//$this->printPre($htmlReply, true); 
		$this->printPre($product_block, true); 

		
		
		
		
		$PRODUCT = $this->_getFromPage('var PRODUCT = ', '};', $htmlReply, 'string')."}";
		$json = json_decode($PRODUCT, true);

    	
    }
    
    
    
    
    
    
	private function _getSelect($product_block, $product_id, $post_id) {
		$select = $this->_getFromPage('<div class="ty-control-group ty-product-options__item', '</select>', $product_block, 'array');
		$content = array();
		if(is_array($select)) {
			$num = 0;
			for($i = 0; $i < count($select); $i++) {
				$select_name_ru = trim(strip_tags("<label".$this->_getFromPage('<label', '</label>', $select[$i], 'string')));
				$select_name_ru = preg_replace('#([\s]*:[\s]*)#', ':', $select_name_ru);
				$select_name_en = $this->str2url($select_name_ru);
				$post_id_2 = $this->_getFromPage('"option_description_'.$product_id.'_', '"', $select[$i], 'string');

				$option_value = $this->_getFromPage('<option', '</option>', $select[$i], 'array');
				if(is_array($option_value)) {
					if($post_id == $post_id_2) {
						for($j = 0; $j < count($option_value); $j++) {
							$option_value_id = $this->_getFromPage('value="', '"', $option_value[$j], 'string');
							$option_value[$j] = str_replace("&nbsp;", "", trim(strip_tags("<option".$option_value[$j])));
							$this->content[$post_id_2][$option_value_id] = preg_replace('#\\((.*?)\\)#i', '', $option_value[$j]);
						}
					} 
					else {
						$content['valid'][$num]['post_id'] = $post_id_2;
						$content['valid'][$num]['name_ru'] = $select_name_ru;
						$content['valid'][$num]['name_en'] = $select_name_en;
						for($j = 0; $j < count($option_value); $j++) {
							$content['valid'][$num]['option'][$j]['option_value_id'] = $this->_getFromPage('value="', '"', $option_value[$j], 'string');
							$option_value[$j] = str_replace("&nbsp;", "", trim(strip_tags("<option".$option_value[$j])));
							if(preg_match('#\([0-9\+\.Р\s]+\)#i', $option_value[$j])) {
								preg_match('#\(\+([0-9]+)\.00Р[\s]*\)#i', $option_value[$j], $matchPrice);
								$content['valid'][$num]['option'][$j]['add_value'] = str_replace($matchPrice[0], '', $option_value[$j]);
								$content['valid'][$num]['option'][$j]['add_price'] = $matchPrice[1];
								$content['valid'][$num]['option'][$j]['add_type'] = 'ruble';
							} else if(preg_match('#\([0-9\+\.%\s]+\)#i', $option_value[$j])) {
								preg_match('#\(\+([0-9]+)%[\s]*\)#i', $option_value[$j], $matchPrice);
								$content['valid'][$num]['option'][$j]['add_value'] = str_replace($matchPrice[0], '', $option_value[$j]);
								$content['valid'][$num]['option'][$j]['add_price'] = $matchPrice[1];
								$content['valid'][$num]['option'][$j]['add_type'] = 'percent';
							} else {
								$content['valid'][$num]['option'][$j]['add_value'] = $option_value[$j];
								$content['valid'][$num]['option'][$j]['add_price'] = 0;
								$content['valid'][$num]['option'][$j]['add_type'] = 'ruble';
							}
						}
						
					}
					

					$num++;
				}
			}
		}
		return $content;
    }
    
    
    
    
    
    
    
    public function setCity($citylink){         
        $settings = array(  'url' => $citylink, 
                            'header' => 1);
        $html = $this->request($settings);
        $cookie = $this->get_headers_from_curl_response($html);
        file_put_contents(dirname(__DIR__)."/files/cookie.txt", $cookie['Set-Cookie']);  
    }
    
    
    /*Сбор займов*/
    public function parse_main(){   
        $content = [];
        $html2 = array('offers' => array());
        $num = 0;
        //Ставим свежие кукисы
        $this->setCity($this->region);
        $cookie = file_get_contents(dirname(__DIR__)."/files/cookie.txt");
        //Запрос 
        $settings = array('post' => "orderBy=0&amount={$this->amount}&period={$this->period}&needCalculation=true&ageStart=0&propositionKey={$this->propositionKey}&skip=0&take=5000",
                            'cookieHeaders' => array('Host: www.sravni.ru','User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:52.0) Gecko/20100101 Firefox/52.0 Cyberfox/52.9.1','Accept: */*','Accept-Language: en-US,en;q=0.5', 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With: XMLHttpRequest','Referer: https://www.sravni.ru/zaimy/?','Content-Length: 102',"Cookie: {$cookie}", 'Connection: keep-alive','If-Modified-Since: *'));        
        $settings['url'] = $this->sitename."zaimy/offers/";
        $htmlReply = $this->request($settings);
        $html = json_decode(gzinflate($htmlReply), true);
        
        if(!isset($html['offers']) || !is_array($html['offers'])){
            return '{"status" : "end", "message":" Займов нет. - Условия: срок - '.$this->period.', сумма - '.$this->amount.'"}';
            //$this->printPre('{"status" : "end", "message":" Займов нет."}', true); 
        }
        $html2['offers'] = array_merge($html2['offers'], $html['offers']);
        if(is_array($html2['offers'])){
            for($i = 0;$i < count($html2['offers']);$i++){    
                //ID займа
                $content[$i]['id'] = $html2['offers'][$i]['defaultOffer']['id'];
                //Название займа
                $content[$i]['name'] = $html2['offers'][$i]['defaultOffer']['name'] ;
                //Ссылка
                $content[$i]['link'] = "https://www.sravni.ru".$html2['offers'][$i]['defaultOffer']['link'] ;
                //Процент в день
                $content[$i]['rate'] = $html2['offers'][$i]['defaultOffer']['rate'] ;
                //ID банка
                $content[$i]['bank_id'] = $html2['offers'][$i]['defaultOffer']['bank']['id'] ;
                //Лого банка
                $content[$i]['bank_logo'] = $html2['offers'][$i]['defaultOffer']['bank']['logo'] ;
                //Алиас банка
                $content[$i]['bank_alias'] = $html2['offers'][$i]['defaultOffer']['bank']['alias'] ;
                //Название банка
                $content[$i]['bank_name'] = $html2['offers'][$i]['defaultOffer']['bank']['name'] ;
                //Урл банка
                $content[$i]['bank_url'] = "https://www.sravni.ru".$html2['offers'][$i]['defaultOffer']['bank']['url'] ;
                //Период кредита
                $content[$i]['period_min'] = $html2['offers'][$i]['defaultOffer']['period']['min'] ;
                $content[$i]['period_max'] = $html2['offers'][$i]['defaultOffer']['period']['max'] ;            
                //Валюта кредита
                $content[$i]['currencySign'] = $html2['offers'][$i]['defaultOffer']['amount']['currencySign'] ;
                $content[$i]['currencyCode'] = $html2['offers'][$i]['defaultOffer']['amount']['currencyCode'] ;                
                //Сумма кредита
                $content[$i]['amountMax'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['amountMax'] ;
                $content[$i]['amountMin'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['amountMin'] ;
                //Срок кредита
                $content[$i]['periodTerm'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['periodTerm'];
                //Партнерское предложение
                $content[$i]['isPartnerOffer'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['isPartnerOffer'] ;
                //Пролонгация
                $content[$i]['allowProlongation'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['allowProlongation']; 
                //Кредит погашается
                $content[$i]['repayment'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['repayment'] ;
                //Особенности
                $content[$i]['specialProductConditions'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['specialProductConditions'] ;
                //Отсрочка
                $content[$i]['hasPostponement'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['hasPostponement'] ;
                //Штрафы
                $content[$i]['hasPenalty'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['hasPenalty'] ;
                //Место заключения договора
                $content[$i]['placeOfExecution'] = implode(', ', $html2['offers'][$i]['defaultOffer']['descriptionParams']['placeOfExecution']) ;
                //Выдача кредита
                $content[$i]['drawingUp'] = implode(', ', $html2['offers'][$i]['defaultOffer']['descriptionParams']['drawingUp']) ;
                //Рассмотрение заявки
                $content[$i]['considerationPeriod'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['considerationPeriod'] ;
                $content[$i]['considerationDaysTo'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['considerationDaysTo'] ;
                $content[$i]['considerationHourTo'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['considerationHourTo'] ;
                $content[$i]['considerationMinuteTo'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['considerationMinuteTo'] ;
                //Возраст заёмщика
                $content[$i]['agePeriod'] = $html2['offers'][$i]['defaultOffer']['descriptionParams']['agePeriod'] ;
                // Ключ
                $content[$i]['propositionKey'] = $html2['offers'][$i]['defaultOffer']['propositionKey'] ;
                //Сортировка
                $content[$i]['sortOrder'] = $html2['offers'][$i]['defaultOffer']['sortOrder'] ;
                
                if(isset($html2['offers'][$i]['offers']) && is_array($html2['offers'][$i]['offers']) && count($html2['offers'][$i]['offers']) > 0){
                    for($j = 0;$j < count($html2['offers'][$i]['offers']);$j++){
                        $html2['offers'][]['defaultOffer'] = $html2['offers'][$i]['offers'][$j];
                    }
                    unset($html2['offers'][$i]['offers']);
                }
                
                $linkIn = $this->sql->query("SELECT * FROM `{$this->db->table}` WHERE `id`='{$content[$i]['id']}'");
                if(!$linkIn->num_rows){
                    $num++;
                    $content[$i] = $this->prepareQuery($content[$i]);
                    $this->sql->query("INSERT INTO `{$this->db->table}`(`id`, `name`, `link`, `rate`, `bank_id`, `bank_logo`, `bank_alias`, `bank_name`, `bank_url`, `period_min`, `period_max`, `currencySign`, `currencyCode`,`amountMax`, `amountMin`,  `periodTerm`, `isPartnerOffer`, `allowProlongation`, `repayment`, `specialProductConditions`, `hasPostponement`, `hasPenalty`, `placeOfExecution`, `drawingUp`, `considerationPeriod`, `considerationDaysTo`, `considerationHourTo`, `considerationMinuteTo`, `agePeriod`, `propositionKey`, `sortOrder`) VALUES(
'{$content[$i]['id']}', '{$content[$i]['name']}','{$content[$i]['link']}','{$content[$i]['rate']}','{$content[$i]['bank_id']}','{$content[$i]['bank_logo']}','{$content[$i]['bank_alias']}','{$content[$i]['bank_name']}','{$content[$i]['bank_url']}','{$content[$i]['period_min']}','{$content[$i]['period_max']}','{$content[$i]['currencySign']}','{$content[$i]['currencyCode']}','{$content[$i]['amountMax']}','{$content[$i]['amountMin']}','{$content[$i]['periodTerm']}','{$content[$i]['isPartnerOffer']}','{$content[$i]['allowProlongation']}','{$content[$i]['repayment']}','{$content[$i]['specialProductConditions']}','{$content[$i]['hasPostponement']}','{$content[$i]['hasPenalty']}','{$content[$i]['placeOfExecution']}','{$content[$i]['drawingUp']}','{$content[$i]['considerationPeriod']}','{$content[$i]['considerationDaysTo']}','{$content[$i]['considerationHourTo']}','{$content[$i]['considerationMinuteTo']}','{$content[$i]['agePeriod']}','{$content[$i]['propositionKey']}','{$content[$i]['sortOrder']}')");
                    if($this->sql->error){  
                        return '{"status" : "error", "message":" Ошибка базы данных: '.__LINE__.' - '.$this->sql->error.'"}';
                        //$this->printPre('{"status" : "error", "message":" Ошибка базы данных: '.__LINE__.' - '.$this->sql->error.'"}', true); 
                        //printf(__LINE__.": Сообщение ошибки: %s<br>\n",  $this->sql->error);
                        //die();                    
                    }
                }
            }
        }
        $this->sql->query("UPDATE `{$this->db->table}` SET `status` = '0'");  
        if($num > 0){
            return '{"status" : "sub", "message":" Займы собраны('.$num.'). - Условия: срок - '.$this->period.', сумма - '.$this->amount.'"}';
            //$this->printPre('{"status" : "sub", "message":" Займы собраны('.$num.'). Начинаем парсинг полной информации новых займов"}', true); 
        }
        else {    
            return '{"status" : "sub", "message":" Новых займов нет. - Условия: срок - '.$this->period.', сумма - '.$this->amount.'"}';
            //$this->printPre('{"status" : "sub", "message":" Новых займов нет. Начинаем парсинг полной информации имеющих займов"}', true); 
        }
        
            
       //echo "<pre>";
        //$this->printPre($content, true);   
        //echo "</pre>";
    }


    /* Сбор информации из займов*/
    public function parse_sub($num_page){  
        setcookie('action', 'sub', time()+3600);
        setcookie('product', $num_page, time()+3600);
        $content = [];        
        $zaymIn = $this->sql->query("SELECT * FROM `{$this->db->table}` WHERE `status`='0'");
        //$zaymIn = $this->sql->query("SELECT * FROM `{$this->db->table}` LIMIT {$num_page},1");
        if(!$zaymIn->num_rows){            
            $this->printPre('{"status" : "end", "message":" Все займы отпарсены."}', true);
        }        
        $fLink = $zaymIn->fetch_assoc();     
        $cookie = file_get_contents(dirname(__DIR__)."/files/cookie.txt");
        //Запрос 
        $settings = array('cookieHeaders' => array('Host: www.sravni.ru','User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:52.0) Gecko/20100101 Firefox/52.0 Cyberfox/52.9.1','Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8','Accept-Language: en-US,en;q=0.5',"Cookie: {$cookie}", 'Connection: keep-alive', 'Upgrade-Insecure-Requests: 1', 'Cache-Control: max-age=0', 'If-Modified-Since: *'));        
        $settings['url'] = $fLink['link'];
        $htmlReply = $this->request($settings);
        if(preg_match('#<title>404</title>#i', $htmlReply)){            
            $this->sql->query("DELETE FROM `{$this->db->table}` WHERE `id`='{$fLink['id']}'");            
            $this->printPre('{"status" : "next", "message":" Займ '.$num_page.' удален"}', true);
        }
        
        $PRODUCT = $this->_getFromPage('var PRODUCT = ', '};', $htmlReply, 'string')."}"; 
        $json = json_decode($PRODUCT, true);
        
        // $this->printPre($settings, true);
                
        $content['description'] = $json['productSummary']['description'].$json['productSummary']['rateDescription'];
        $content['cityName'] = $json['cityName'];
        $content['organization_name'] = $json['organization']['name'];
        $content['organization_fullName'] = $json['organization']['fullName'];
        $content['organization_alias'] = $json['organization']['alias'];
        $content['organization_license'] = $json['organization']['license'];
        $content['organization_address'] = $json['organization']['address'];
        $content['organization_phone'] = $json['organization']['phone'];
        $content['organization_webUrl'] = $json['organization']['webUrl'];
        $content['organization_description'] = $json['organization']['description'];
        $content['organization_bik'] = $json['organization']['bik'];
        $content['organization_inn'] = $json['organization']['inn'];
        $content['organization_kpp'] = $json['organization']['kpp'];
        $content['organization_ogrn'] = $json['organization']['ogrn'];
        $content['organization_okpo'] = $json['organization']['okpo'];
        $content['organization_id'] = $json['organization']['id'];
        
        
        if(isset($_GET['print'])){
            switch($_GET['print']){
                case 'content' : {$this->printPre($content, true);} break;
                case 'json' : {$this->printPre($json, true);} break;
                case 'db' : {$this->printPre($fLink, true);} break;
            }
        }
          
        //Требования
        $num = 0;
        $spec = [];
        if(is_array( $json['requirements'])){
            if(is_array( $json['conditions'])){ 
                $json['requirements'] = array_merge($json['requirements'], $json['conditions']);
            }
            for($i = 0;$i < count( $json['requirements']);$i++){
                if(isset($json['requirements'][$i]['name']) && strlen($json['requirements'][$i]['name']) > 1){ 
                    $content['spec']['name'][$num] = trim($json['requirements'][$i]['name']); 
                    switch($content['spec']['name'][$num]){
                        case "Выдача кредита" : { $content['spec']['post'][$num] = 'drawingUp'; }; break;
                        case "Место заключения договора" : { $content['spec']['post'][$num] = 'placeOfExecution'; }; break;
                        case "Дополнительные условия" : { $content['spec']['post'][$num] = 'additionalTerms'; }; break;
                        case "Отсрочка" : { $content['spec']['post'][$num] = 'Postponement'; }; break;
                        case "Рассмотрение заявки" : { $content['spec']['post'][$num] = 'considerationPeriod'; }; break;
                        case "Подтверждение платёжеспособности" : { $content['spec']['post'][$num] = 'evidenceOfSolvency.'; }; break;
                        case "Необходимые документы" : { $content['spec']['post'][$num] = 'requiredDocs'; }; break;
                        case "Постоянный доход" : { $content['spec']['post'][$num] = 'fixedIncome'; }; break;
                        case "Мобильный телефон" : { $content['spec']['post'][$num] = 'hasPhone'; }; break;
                        case "Наличие постоянной регистрации в регионе обращения" : { $content['spec']['post'][$num] = 'hasRegistration'; }; break;
                        case "Наличие постоянной или временной регистрации в регионе обращения" : { $content['spec']['post'][$num] = 'hasTempRegistration'; }; break;
                        case "Гражданство РФ" : { $content['spec']['post'][$num] = 'citizenRF'; }; break;
                        case "Возраст заёмщика" : { $content['spec']['post'][$num] = 'agePeriod'; }; break;
                        case "Хорошая кредитная история или отсутствие негативной кредитной истории" : { $content['spec']['post'][$num] = 'hasGoodCreditHistory'; }; break;
                        case "Отсутствие негативной кредитной истории" : { $content['spec']['post'][$num] = 'noBadCreditHistory'; }; break;
                        case "Срок действия решения" : { $content['spec']['post'][$num] = 'decisionExpire'; }; break;
                        case "Страхование" : { $content['spec']['post'][$num] = 'insurance'; }; break;
                        case "Возраст в момент получения кредита (для мужчин)" : { $content['spec']['post'][$num] = 'manAge'; }; break;
                        case "Возраст в момент получения кредита (для женщин)" : { $content['spec']['post'][$num] = 'womanAge'; }; break;
                        case "Штрафы" : { $content['spec']['post'][$num] = 'Penalties'; }; break;
                        default : {$content['spec']['post'][$num] = $this->str2url($content['spec']['name'][$num]);}
                    }
                    if(isset($json['requirements'][$i]['list']) 
                    &&  is_array($json['requirements'][$i]['list']) && count($json['requirements'][$i]['list'])>0){
                        $content['spec']['value'][$num] = implode(', ', $json['requirements'][$i]['list']);
                    }
                    else if(strlen($json['requirements'][$i]['value']) > 0){
                        $content['spec']['value'][$num] = trim(strip_tags($json['requirements'][$i]['value']));
                    }
                    else{
                        $content['spec']['value'][$num] = ($json['requirements'][$i]['check'] == 1) ? 1 : 0 ;
                    }
                    $content['spec']['value'][$num] = $this->prepareQuery($content['spec']['value'][$num]);
                    $spec[] = "`{$content['spec']['post'][$num]}`='{$content['spec']['value'][$num]}'";
                    $specIn = $this->sql->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$this->db->table}' AND COLUMN_NAME ='{$content['spec']['post'][$num]}'");
                    if(!$specIn->num_rows){
                        $this->sql->query("ALTER TABLE `{$this->db->table}` ADD `{$content['spec']['post'][$num]}` text NOT NULL");                                             
                        if($this->sql->error){
                            $this->printPre('{"status" : "error", "message":" Ошибка базы данных: '.__LINE__.' - '.$this->sql->error.'"}', true);                   
                        }
                        $this->sql->query("INSERT INTO `{$this->db->table_spec}`(`spec_name`, `spec_post`) VALUES ('{$content['spec']['name'][$num]}','{$content['spec']['post'][$num]}')");                        
                        if($this->sql->error){
                            $this->printPre('{"status" : "error", "message":" Ошибка базы данных: '.__LINE__.' - '.$this->sql->error.'"}', true);                   
                        }
                    }
                    $num++;  
                }
            }
        }
        //
        $specQuery = "";
        $content = $this->prepareQuery($content);
        if(is_array($spec) && count($spec) > 0){
            $specQuery = implode(',', $spec).",";
        }
        $this->sql->query("UPDATE `{$this->db->table}` SET {$specQuery} `description` = '{$content['description']}', `cityName` = '{$content['cityName']}', `organization_license`='{$content['organization_license']}', `organization_name` = '{$content['organization_name']}', `organization_fullName` = '{$content['organization_fullName']}', `organization_alias` = '{$content['organization_alias']}', `organization_address` = '{$content['organization_address']}', `organization_phone` = '{$content['organization_phone']}', `organization_webUrl` = '{$content['organization_webUrl']}', `organization_description` = '{$content['organization_description']}', `organization_bik` = '{$content['organization_bik']}', `organization_inn` = '{$content['organization_inn']}', `organization_kpp` = '{$content['organization_kpp']}', `organization_ogrn` = '{$content['organization_ogrn']}', `organization_okpo` = '{$content['organization_okpo']}', `organization_id` = '{$content['organization_id']}', `status`='1' WHERE `id`='".$fLink['id']."'");  
        if($this->sql->error){
            $this->printPre('{"status" : "error", "message":" Ошибка базы данных: '.__LINE__.' - '.$this->sql->error.'"}', true); 
            //printf(__LINE__.": Сообщение ошибки: %s<br>\n",  $this->sql->error);
            //die();                    
        }
        $this->printPre('{"status" : "next", "message":" Парсим займ '.$num_page.'"}', true);
    }



    //security prepare string from SQL inject
    private function prepareQuery($query){        
        if(is_array($query)){
            foreach($query as $key => $value){
                $query[$key] = $this->prepareQuery($value);
            }
        }  
        else if(is_object($query)){
            foreach($query as $key => $value){
                $query->$key = $this->prepareQuery($value);
            }
        }        
        else { 
            $query = $this->sql->real_escape_string($query);
        }
        return $query;
    }
     
}

?>