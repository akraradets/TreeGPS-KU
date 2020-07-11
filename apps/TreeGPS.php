<?php

// ขั้นตอนเก็บข้อมูล
// 1. กดแชร์โลเคชั่น
// 2. กดยืนยัน
// 3. ชื่อต้นไม้:ขนาด
// 4. กดยืนยัน
// 5.ส่งรูป
// 6. กดยืนยัน

class TreeGPS extends Line_Apps{
  var $URL = "https://kunnaree.com/AIT-TreeGPS/images/";
  var $UPLOAD_DIR = "./images/";
  var $fromSubmitConfirmMenu = false;
  var $discardImage = false;

  function on_follow(){
    // Add new friend
    $this->resetSession();
    return "ยินต้อนรับสู่ TreeGPS@KU คุณ {$this->profile->display_name}";
  }

  function on_message($message){
    $datas = file_get_contents('php://input');
    $deCode = json_decode($datas,true);
    $userid = $deCode['events'][0]['source']['userId'];
    $this->session->set('userid',$userid);

    // handle when receive message
    $type = $message['type'];
    switch ($type) {
      case 'text':
      case 'location':
      case 'image':
        $func = "message_{$type}";
        $answer = $this->$func($message);
        break;
      default:
        $answer = "Type '{$type}' is not support now.";
        break;
    }

    // Reply messages array
    $messages[] = $answer;
    if($this->fromSubmitConfirmMenu == false){
      $messages[] = $this->template();
    }
    return $messages;
  }

  /* My own function */
  function resetSession($new = false){
    $this->session->set('tree', NULL);
    $this->session->set('size', NULL);
    $this->session->set('latitude', NULL);
    $this->session->set('longitude', NULL);
    $this->discardImage = false;

    if($new == false){
      if($this->session->get('image') != NULL){
        unlink($this->UPLOAD_DIR . $this->session->get('image'));
      }
    }
    $this->session->set('image', NULL);
  }

  function submit(){
    $answer = "";
    $valid = true;
    $hasImage = true;
    if( $this->session->get('latitude') == NULL || $this->session->get('longitude') == NULL){
      $answer .= "ไม่มีค่า Location - แชร์ Location ก่อน\n";
      $valid = false;
    }
    if( $this->session->get('image') == NULL){
      // $answer .= "ยังไม่ได้ส่งรูป - ถ่ายรูปมาก่อนจ่ะ\n";
      // $valid = false;
      $hasImage = false;
    }
    
    if( $this->session->get('tree') == NULL){
      $answer .= "ยังไม่มีชื่อต้นไม้\n";
      $valid = false;
    }

    if( $this->session->get('size') == NULL){
      $answer .= "ยังไม่มีขนาดต้นไม้\n";
      $valid = false;
    }
    
    // save?
    if($valid == false){
      $answer = "การ Submit ล้มเหลวจ่ะ\n" . $answer;
      $answer = substr($answer, 0, -1);
      return $answer;
    }
    else{
      if($this->discardImage == false && $hasImage == false){
        return $this->submitConfirm();
      }
      else{
        return $this->saveToDB();
      }
    }
  }
  
  function saveToDB(){
    // userid
    $userid = $this->session->get('userid');
    // username
    $username = $this->profile->display_name;
    // name
    $name = $this->session->get('tree');
    // size
    $size = $this->session->get('size');
    // location
    $lat= $this->session->get('latitude');
    $long = $this->session->get('longitude');
    // image
    $image = $this->session->get('image') == NULL ? "" : $this->session->get('image');
    if($this->discardImage == false){
      $image = $this->URL . $image;
    }
    // timestamp

    $db = new Database();
    $result = $db->query("SET NAMES UTF8");
    $query = "INSERT INTO trees (name,size,latitude,longitude,image,userid,username) VALUES 
              ('{$name}','{$size}',{$lat},{$long},'{$image}','{$userid}','{$username}')";
    $result = $db->query($query);
    $db->close();
    if($result == true){
      $this->resetSession(true);
      return "บันทึกสมบูรณ์ดีเยี่ยม!!";
    }
    return "บันทึกล้มเหลว แย่แล้วววววววว";
  }


  function message_text($message){
    $type = $message['type'];
    $text = $message['text'];
    if($text == 'Reset'){
      $this->resetSession();
      return "เริ่มต้นใหม่แล้ว";
    }
    elseif($text == 'Submit'){
      return $this->submit();
    }
    elseif(strpos($text, ':') !== false){
      $tmps = explode(':',$text);
      $tree = $tmps[0];
      $size = $tmps[1];
      $this->session->set('tree', $tree);
      $this->session->set('size', $size);
      return "ได้รับชื่อและขนาดแล้ว";
    }
    elseif($text == "USERID"){
      // $datas = file_get_contents('php://input');
      // $deCode = json_decode($datas,true);
      // $userid = $deCode['events'][0]['source']['userId'];
      $userid = $this->session->get('userid');
      return $userid;

    }
    elseif($text == "SubmitConfirm"){
      $this->discardImage = true;
      return $this->submit();
    }
    elseif($text == "SubmitCancel"){
      return "ไปถ่ายรูปเลยจ้า";
    } 
    else{
      return "คำสั่งไม่ถูกต้อง\nถ้าต้องตั้งชื่อต้นไม้และขนาดให้ส่งแบบนี้\nชื่อต้นไม้:ขนาด";
    }
  }

  function message_location($message){
    // {
    //   "type": "location",
    //   "title": "my location",
    //   "address": "〒150-0002 東京都渋谷区渋谷２丁目２１−１",
    //   "latitude": 35.65910807942215,
    //   "longitude": 139.70372892916203
    // }
    $this->session->set('latitude', $message['latitude']);
    $this->session->set('longitude', $message['longitude']);
    return "ได้รับค่า Location แล้ว - จัดไปขุ่นพี่!!!";
  }

  function message_image($message){
    $results = $this->getContent($message['id']);
    if($results['result'] == 'S'){
      if($this->session->get('image') != NULL){
        unlink($this->UPLOAD_DIR . $this->session->get('image'));
      }
      $file = uniqid() . '.png';
      $this->session->set('image', $file);
      file_put_contents($this->UPLOAD_DIR . $file, $results['response']);
      return "ได้รับรูปแล้ว - ทำได้ดีมาก";
    }
    else{
      return 'มี error อะ - ' . $results['message'];
    }
    return $message['id'] . $message['contentProvider']['type'];
  }
  function getContent($msgId){
    $option = new Option();
    $token = $option->get('channel_access_token');
    $datasReturn = [];
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL =>   "https://api.line.me/v2/bot/message/".$msgId."/content",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
      CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer ".$token,
        "cache-control: no-cache"
      ),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if($err){
      $datasReturn['result'] = 'E';
      $datasReturn['message'] = $err;
    }else{
      $datasReturn['result'] = 'S';
      $datasReturn['message'] = 'Success';
      $datasReturn['response'] = $response;
    }
    return $datasReturn;
  }

  function submitConfirm(){
    $template = array('type' => 'template',
        'altText' => 'เพื่อใช้ LINE Bot นี้ โปรคใช้ผ่าน มือถือ ของท่าน',
        'template'=> array(
          'type'=> 'confirm',
          'text'=> "ยืนยันการส่งแบบไม่มีรูป",
          'actions'=> array(
            array('type'=> 'message','label'=> 'ยืนยัน','text'=> 'SubmitConfirm'),
            array('type'=> 'message','label'=> 'ยกเลิก','text'=> 'SubmitCancel')
          )
        )
      );
    $this->fromSubmitConfirmMenu = true;
    return $template;
  }

  function template(){
    $latitude = $this->session->get('latitude') == NULL ? 'ว่าง' :  $this->session->get('latitude');
    $longitude = $this->session->get('longitude') == NULL ? 'ว่าง' :  $this->session->get('longitude') ;
    $tree = $this->session->get('tree') == NULL ? 'ว่าง' : $this->session->get('tree');
    $size = $this->session->get('size') == NULL ? 'ว่าง' : $this->session->get('size');
    $file = $this->session->get('image');

    $latitude = number_format( $latitude , 4 );
    $longitude = number_format( $longitude , 4 );

    $text = "ชื่อ:{$tree}";
    $text .= "\nขนาด:{$size}";
    $text .= "\nGPS:{$latitude},{$longitude}";
    if($file == NULL){
      $text .= "\nรูป:ยังไม่ได้อัพ";
    }

    $template = array('type' => 'template',
        'altText' => 'เพื่อใช้ LINE Bot นี้ โปรคใช้ผ่าน มือถือ ของท่าน',
        'template'=> array(
          'type'=> 'buttons',
          "title"=> "แบบฟอร์ม โดย {$this->profile->display_name}",
          'text'=> $text,
          // "defaultAction"=> array(
          //     "type"=> "uri",
          //     "label"=> "View detail",
          //     "uri"=> "http://example.com/page/123"
          // ),
          'actions'=> array(
            array('type'=> 'message','label'=> 'ส่งฟอร์ม','text'=> 'Submit')
            // array('type'=> 'message','label'=> 'เริ่มใหม่','text'=> 'Reset')
          )
        )
      );
    if($file != NULL){
      $image = $this->URL . $file;
      $template['template']["thumbnailImageUrl"] = $image;      
      $template['template']["imageAspectRatio"] = "rectangle";
      $template['template']["imageSize"] = "cover";
      $template['template']["imageBackgroundColor"] = "#FFFFFF";
    }
    return $template;
  }
}
