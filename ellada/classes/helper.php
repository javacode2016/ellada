<?php 

class Helper{
    private $collect_array = [];
    //private $index;
    public function __construct() {
        //$this->index = 0;
    } 
    
    public function add_url_to_multi_handle($mh, $url_list) {
        static $index= 0;
        if (isset($url_list[$index])) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_list[$index]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            curl_multi_add_handle($mh, $ch);
            $index++;
            return true;
        } 
        else {
            // добавление новых URL завершено
            return false;
        }
    }
        
    
    public function multiple_request($from, $to, $link){
        $excluded_domains = array('localhost', 'www.mydomain.com');
        $max_connections = 10;
        // инициализация переменных
        $url_list = array();
        $working_urls = array();
        $dead_urls = array();
        $not_found_urls = array();
        $active = null;
        for($i = $from;$i < $to;$i++){
            $url_list[] = $link.$i;
        }
        // убираем дубликаты
        //$url_list = array_values(array_unique($url_list));
        if (!$url_list) {
            die('No URL to check');
        }
        // 1. множественный обработчик
        $mh = curl_multi_init();    
        // 2. добавляем множество URL
        for ($i = 0; $i < $max_connections; $i++) {
            $this->add_url_to_multi_handle($mh, $url_list);
        }
        // 3. инициализация выполнения
        do { $mrc = curl_multi_exec($mh, $active); } 
        while ($mrc == CURLM_CALL_MULTI_PERFORM);    
        // 4. основной цикл
        while ($active && $mrc == CURLM_OK) {   
            // 5. если всё прошло успешно
            if (curl_multi_select($mh) != -1) {    
             // 6. делаем дело
             do { $mrc = curl_multi_exec($mh, $active);} 
             while ($mrc == CURLM_CALL_MULTI_PERFORM);    
             // 7. если есть инфа?
             if ($mhinfo = curl_multi_info_read($mh)) {
                 // это значит, что запрос завершился    
                 // 8. извлекаем инфу
                 $chinfo = curl_getinfo($mhinfo['handle']);    
                 // 9. мёртвая ссылка?
                 if (!$chinfo['http_code']) {
                     $dead_urls[]= $chinfo['url'];    
                 // 10. 404?
                 } 
                 else if ($chinfo['http_code'] == 404) {
                     $not_found_urls[]= $chinfo['url'];    
                 // 11. рабочая
                 } 
                 else {
                     $working_urls[]= $chinfo['url'];
                 }
                 // 12. чистим за собой
                 // в случае зацикливания, закомментируйте данный вызов 
                 curl_multi_remove_handle($mh, $mhinfo['handle']); 
                 curl_close($mhinfo['handle']);
    
                // 13. добавляем новый url и продолжаем работу
                if ($this->add_url_to_multi_handle($mh, $url_list)) {    
                    do {
                        $mrc = curl_multi_exec($mh, $active);
                    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                }
            }
        }
    }
    // 14. завершение
    curl_multi_close($mh);
    //echo "==Dead URLs==\n";
    //echo implode("\n",$dead_urls) . "\n\n";
    
    //echo "==404 URLs==\n";
    //echo implode("\n",$not_found_urls) . "\n\n";
    
   // echo "==Working URLs==\n";
    //echo implode("\n",$working_urls);
    //$t= get_defined_vars();
       // print_r($t);
    }
    
    
    public function FileSizeConvert($bytes){
        $bytes = floatval($bytes);
            $arBytes = array(
                0 => array(
                    "UNIT" => "TB",
                    "VALUE" => pow(1024, 4)            ),
                1 => array(
                    "UNIT" => "GB",
                    "VALUE" => pow(1024, 3)            ),
                2 => array(
                    "UNIT" => "MB",
                    "VALUE" => pow(1024, 2)            ),
                3 => array(
                    "UNIT" => "KB",
                    "VALUE" => 1024 ),
                4 => array(
                    "UNIT" => "B",
                    "VALUE" => 1),
            );
        foreach($arBytes as $arItem)    {
            if($bytes >= $arItem["VALUE"])        {
                $result = $bytes / $arItem["VALUE"];
                $result = str_replace(".", "," , strval(round($result, 2)))." ".$arItem["UNIT"];
                break;
            }
        }
        return $result; 
    }
    
    public function printPre($array, $die = false, $pre = false){
        if($pre){
            echo "<pre>";
        }
		if(!is_array($array) && !is_object($array)) {
			echo $array;
		}
		else {
			print_r($array);
		}
        if($pre){            
            echo "</pre>";
        }
        if($die) {
          die();  
        }
    }
    
    
    public function random_str($num = 30, $list) {    
        $promocode = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, $num); 
        $promoList = file_get_contents($list);
        if(preg_match('#'.$promocode.'#i', $promoList)){
            random_str($num); 
        }
        else {
            return $promocode;
        }
    }
    
    //////////////////////////////////////////////////
    //////////// ЗАПРОС  ////////
    //////////////////////////////////////////////////
    public function _get($url, $post = false){       
        $ch = curl_init($url); 
        curl_setopt ($ch, CURLOPT_HEADER, 0);  
        curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT  5.1; en-US; rv:1.9.0.3) Gecko/2008092417 Firefox/3.0.3'); 
        curl_setopt ($ch, CURLOPT_REFERER,  $url); 
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,1);
        
        //curl_setopt ($ch, CURLOPT_COOKIEJAR, 'cookie.txt'); 
        //curl_setopt ($ch, CURLOPT_COOKIEFILE,'cookie.txt');  
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0); 
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0); 
        
        
        curl_exec ($ch); 
        $result = curl_multi_getcontent ($ch); 
        curl_close ($ch);
        $result = iconv('windows-1251', 'UTF-8', $result);
        return $result; 
    }
    
    //пост запросы
    public function request($settings ){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $settings['url'] ); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, (isset($settings['header'])) ? 1 : 0);// пустые заголовки
        //curl_setopt($ch, CURLOPT_NOBODY, (isset($settings['nobody'])) ? 1 : 0);// без тела
        //curl_setopt($ch, CURLOPT_COOKIESESSION, (isset($settings['session'])) ? 1 : 0);// новая сессия
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);// таймаут4
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if(isset($settings['cookieFile'])){
            curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/'.$settings['cookieFile']); // сохранять куки в файл
            curl_setopt($ch, CURLOPT_COOKIEFILE,  dirname(__FILE__).'/'.$settings['cookieFile']);
        }
        if(isset($settings['cookieHeaders'])){
            curl_setopt ($ch, CURLOPT_HTTPHEADER, $settings['cookieHeaders']);
        }
        if(isset($settings['post'])){
            curl_setopt($ch, CURLOPT_POSTFIELDS, $settings['post']);
            curl_setopt($ch, CURLOPT_POST, 1 ); // использовать данные в post
        }
        if(isset($settings['cookieStr'])){
            curl_setopt ($ch, CURLOPT_COOKIE, $settings['cookieStr']);
        }    
        if(isset($settings['proxy'])){            
            $proxy = explode(':', $settings['proxy']);                    
            curl_setopt($ch, CURLOPT_PROXY, $proxy[0]);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxy[1]);
        }
        $data = curl_exec($ch);
        if(curl_exec($ch) === false){
            echo 'Ошибка curl: ' . curl_error($ch);
        }
        curl_close($ch);
        if(isset($settings['iconv'])){
            //iconv('windows-1251', 'UTF-8', $result);
            $data = iconv($settings['iconv']['from'], $settings['iconv']['to'], $data);
        }
        return $data;
    }
    
    
    //парс со страницы  
    public function _getFromPage($start, $end, $result, $type){
        if($end === false || $end == '' ) { 
            return str_replace($start, '', substr($result, strpos($result, $start)));
        }
        $start = preg_quote($start);  
        $end = preg_quote($end);  
        if($type == "array") {  
            preg_match_all('#'.$start.'(.*?)'.$end.'#s', $result, $events);  
        }  
        if($type == "string"){  
            preg_match('#'.$start.'(.*?)'.$end.'#s', $result, $events);  
        } 
        if(empty($events[1])) {return false;}
        else { return $events[1];} 
    }  
    
    //Собираем все подмассивы в единый массив
    public function collect_array_func($array){
        if(is_array($array)){
            foreach ($array as $key => $value) {
                if(is_array($value)){
                    $this->collect_array = collect_array_func($value); 
                }
                else {                
                    $this->collect_array[] = $value;
                }
            }
        }
        else {
            $this->collect_array[] = $array;
        }
        return array_values(array_unique($this->collect_array));
    }
    
    
    
    public function checkProxy($proxy){
        //$proxyauth = 'user:password';  
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://dynupdate.no-ip.com/ip.php');
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        //curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);   
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $curl_scraped_page = curl_exec($ch);
        curl_close($ch);
        $status = explode("\n", $curl_scraped_page);
        if(preg_match('/200/i', $status[0])){
            return true;
        }
        else {
            return false;
        }
    }
    /*
    if(file_exists($file)){
        $dif = differenceBetween2dates(time(), filemtime($file));
        if($dif['status']){
            if($dif['hour'] >= 12){
            //обновляем если прошло больше 12 часов с момента последнего обновления
                createChapterFile('chapter.txt');
            }
        }
    }
    */
    /*
     function differenceBetween2dates($date1, $date2){
        $r = array();
        if(is_numeric($date1) && is_numeric($date2) ){
            if($date1 >= $date2){
                $sezar = ($date1 - $date2);                    
                $h = $sezar/3600 ^ 0 ;
                $m = ($sezar-$h*3600)/60 ^ 0 ;
                $s = $sezar-$h*3600-$m*60 ;
                $r['hour'] = ($h<10?"0".$h:$h);
                $r['min'] = ($m<10?"0".$m:$m);
                $r['sec'] = ($s<10?"0".$s:$s);
                $r['status'] = true;
                $r['message'] = ($h<10?"0".$h:$h)." ч. ".($m<10?"0".$m:$m)." мин. ".($s<10?"0".$s:$s)." сек.";
            }
            else {
                $r['status'] = false;
                $r['message'] = 'Дата-1 должна быть больше или равна Дата-2';
            }
        }
        else {
                $r['status'] = false;
                $r['message'] = 'Дата-1 или Дата-2 не являются цифровыми';
        }
        return $r;
    }
    
    
    */
    public function get_headers_from_curl_response($response){
        $headers = array();
        $setcookie = array();
        $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));
        $head_arr = explode("\r\n", $header_text);
        for($i = 0;$i < count($head_arr);$i++){
            if (preg_match('#Set-Cookie#i', $head_arr[$i])){
                list ($key, $value) = explode(':', $head_arr[$i]);
                if(!isset($headers[$key])) { $headers[$key] = ''; }
                
                list ($cookieName, $cookieValue) = explode('=', $value);
                $cookieName = trim($cookieName);
                if(!isset($setcookie[$cookieName])) { $setcookie[$cookieName] = ''; }
                $setcookie[$cookieName] = $value;
            } 
        }
        $headers['Set-Cookie'] = implode('', $setcookie);
        return $headers;
    }
    
    
    
    
    ///////////////////////
    ///работа с массивом///
    ///////////////////////
    public function editArr(&$item, $value, $rule){
        if(is_string($rule)){
            if(preg_match('#strip#i', $rule)){
                $item = strip_tags($item);
            }
            if(preg_match('#trim#i', $rule)){
                $item = trim($item);
                $item = str_replace(array("\n", "\r"), '', $item);
            }
            if(preg_match('#href#i', $rule)){
                $item = _getFromPage('href="', '"', $item, 'string');
            }
        }
        if(is_array($rule)){
            if(isset($rule['addBefore'])){
                $item = $rule['addBefore'].$item;
            }
            if(isset($rule['addAfter'])){
                $item = $item.$rule['addAfter'];
            }
            if(isset($rule['replace'])){
                $item = preg_replace('`'.$rule['replace'][0].'`i', $rule['replace'][1], $item);
            }
            if(isset($rule['getFromPage'])){
                preg_match('`'.$rule['getFromPage'][0].'(.*?)'.$rule['getFromPage'][1].'`s', $item, $events);
                $item = $events[1];
            }
            if(isset($rule['convert'])){
                //$item = iconv('windows-1251', 'UTF-8', $result);
                $item = iconv($rule['convert'][0], $rule['convert'][1], $item);
            }
        }
    }
     
    
     /////////////////////
    //работа со строкой//
    /////////////////////
    public function editStr($item, $rule, $value = false){
        if(preg_match('/strip/i', $rule)){
            $item = strip_tags($item);
        }
        if(preg_match('/trim/i', $rule)){
            $item = trim($item);
        }
        if(preg_match('/href/i', $rule)){        
            if(preg_match('/href="/i', $item)){
                $item = _getFromPage('href="', '"', $item, 'string');
            }       
            if(preg_match("/href='/i", $item)){
                $item = _getFromPage("href='", "'", $item, 'string');
            }
        }
        if(preg_match('/src/i', $rule)){        
            if(preg_match('/src="/i', $item)){
                $item = _getFromPage('src="', '"', $item, 'string');
            }       
            if(preg_match("/src='/i", $item)){
                $item = _getFromPage("src='", "'", $item, 'string');
            }
        }
        if(preg_match('/replace/i', $rule)){
            $item = preg_replace('`'.$value.'`i', '', $item);
        }
        return $item;
    }
    
    //////////////////////////////
    // Оптимизация изображений ///
    //////////////////////////////
    public function compressImage($image_link, $ImageQuality){    
        //Open Source Image directory, loop through each Image and resize it.
        $checkValidImage = @getimagesize($image_link);
        //Continue only if 2 given parameters are true
        if (file_exists($image_link) && $checkValidImage)   {
            //Image looks valid, resize.
            resizeImage($image_link, $image_link, $checkValidImage[0], $checkValidImage[1], $ImageQuality);
        }
    }
    //Function that resizes image.
    public function resizeImage($SrcImage, $DestImage, $MaxWidth, $MaxHeight, $ImageQuality){
        list($iWidth, $iHeight, $type) = getimagesize($SrcImage);
        $ImageScale = min($MaxWidth / $iWidth, $MaxHeight / $iHeight);
        $NewWidth = ceil($ImageScale * $iWidth);
        $NewHeight = ceil($ImageScale * $iHeight);
        $NewCanves = imagecreatetruecolor($NewWidth, $NewHeight);
        switch (strtolower(image_type_to_mime_type($type))) {
            case 'image/jpeg': $NewImage = imagecreatefromjpeg($SrcImage); break;
            case 'image/JPEG': $NewImage = imagecreatefromjpeg($SrcImage); break;
            case 'image/png': $NewImage = imagecreatefrompng($SrcImage); break;
            case 'image/PNG':  $NewImage = imagecreatefrompng($SrcImage); break;
            case 'image/gif': $NewImage = imagecreatefromgif($SrcImage);  break;
            default: return false;
        }
        // Resize Image
        if (imagecopyresampled($NewCanves, $NewImage, 0, 0, 0, 0, $NewWidth, $NewHeight,
            $iWidth, $iHeight)) {
            // copy file
            if (imagejpeg($NewCanves, $DestImage, $ImageQuality)) {
                imagedestroy($NewCanves);
                return true;
            }
        }
    }
    
    
    //РАЗМЕР ФАЙЛА
    public function get_filesize($file){
        $data = get_headers($file, true);
        $filesize = isset($data['Content-Length'])?(int) $data['Content-Length']:0;
        if($filesize > 1024) {
           $filesize = ($filesize/1024);
            if($filesize > 1024)     {
                $filesize = ($filesize/1024);
                if($filesize > 5) { return false;    }
                else{ return true;  }       
            }
            else  { return true;  }  
        }
        else{ return true;  }
    }
    
    ////////////////////////
    ///транслит для алиас///
    ////////////////////////
    public function rus2translit($string) {
        $converter = array(
            'а' => 'a',   'б' => 'b',   'в' => 'v',
            'г' => 'g',   'д' => 'd',   'е' => 'e',
            'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
            'и' => 'i',   'й' => 'y',   'к' => 'k',
            'л' => 'l',   'м' => 'm',   'н' => 'n',
            'о' => 'o',   'п' => 'p',   'р' => 'r',
            'с' => 's',   'т' => 't',   'у' => 'u',
            'ф' => 'f',   'х' => 'h',   'ц' => 'c',
            'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
            'ь' => '',    'ы' => 'y',   'ъ' => '',
            'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
            ',' => '',      '"' => '',
            "'" => '',
            
            'А' => 'A',   'Б' => 'B',   'В' => 'V',
            'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
            'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
            'И' => 'I',   'Й' => 'Y',   'К' => 'K',
            'Л' => 'L',   'М' => 'M',   'Н' => 'N',
            'О' => 'O',   'П' => 'P',   'Р' => 'R',
            'С' => 'S',   'Т' => 'T',   'У' => 'U',
            'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
            'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
            'Ь' => '',    'Ы' => 'Y',   'Ъ' => '',
            'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
        );
        return strtr($string, $converter);
    }
    public function str2url($str) {
        // переводим в транслит
        $str = $this->rus2translit($str);
        // в нижний регистр
        $str = strtolower($str);
        // заменям все ненужное нам на "-"
        $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);
        // удаляем начальные и конечные '-'
        $str = trim($str, "-");
        return $str;
    }
}
?>