<?php
$theme=parse_ini_file(THEME.DS.'theme.ini',true);
$notification='';
if($args[0]=="confirm"){
	if($_POST['emailtrap']==''){
		$email=filter_input(INPUT_POST,'email',FILTER_SANITIZE_EMAIL);
		$rewards=filter_input(INPUT_POST,'rewards',FILTER_SANITIZE_STRING);
		$uid=isset($_SESSION['uid'])?$_SESSION['uid']:0;
		if(filter_var($email,FILTER_VALIDATE_EMAIL)){
			$s=$db->prepare("SELECT id,status FROM login WHERE email=:email");
			$s->execute(array(':email'=>$email));
			if($s->rowCount()>0){
				$ru=$s->fetch(PDO::FETCH_ASSOC);
				if($ru['status']=='delete'||$ru['status']=='disabled')
					$notification.=$theme['settings']['account_suspend'];
				else
					$uid=$ru['id'];
			}else{
				$business=filter_input(INPUT_POST,'business',FILTER_SANITIZE_STRING);
				$address=filter_input(INPUT_POST,'address',FILTER_SANITIZE_STRING);
				$suburb=filter_input(INPUT_POST,'suburb',FILTER_SANITIZE_STRING);
				$city=filter_input(INPUT_POST,'city',FILTER_SANITIZE_STRING);
				$state=filter_input(INPUT_POST,'state',FILTER_SANITIZE_STRING);
				$postcode=filter_input(INPUT_POST,'postcode',FILTER_SANITIZE_STRING);
				$phone=filter_input(INPUT_POST,'phone',FILTER_SANITIZE_STRING);
				$username=explode('@',$email);
				$q=$db->prepare("INSERT INTO login (username,password,email,business,address,suburb,city,state,postcode,phone,status,active,rank,ti) VALUES (:username,'',:email,:business,:address,:suburb,:city,:state,:postcode,:phone,'','1','0',:ti)");
				$q->execute(array(':username'=>$username[0],':email'=>$email,':business'=>$business,':address'=>$address,':suburb'=>$suburb,':city'=>$city,':state'=>$state,':postcode'=>$postcode,':phone'=>$phone,':ti'=>$ti));
				$id=$db->lastInsertId();
				$q=$db->prepare("UPDATE login SET username=:username WHERE id=:id");
				$q->execute(array(':id'=>$id,':username'=>$username[0].$id));
				$su=$db->prepare("SELECT id FROM login WHERE id=:id");
				$su->execute(array(':id'=>$id));
				$ru=$su->fetch(PDO::FETCH_ASSOC);
				$uid=$r['id'];
			}
			$r=$db->query("SELECT MAX(id) as id FROM orders")->fetch(PDO::FETCH_ASSOC);
			$sr=$db->prepare("SELECT id,quantity,tis,tie FROM rewards WHERE code=:code");
			$sr->execute(array(':code'=>$rewards));
			if($sr->rowCount()>0){
				$reward=$sr->fetch(PDO::FETCH_ASSOC);
				if(!$reward['tis']>$ti&&!$reward['tie']<$ti)
					$rewards['id']=0;
				if($reward['quantity']<1)
					$reward['id']=0;
				else{
					$sr=$db->prepare("UPDATE rewards SET quantity=:quantity WHERE code=:code");
					$sr->execute(array(':quantity'=>$rewards['quantity']-1,':code'=>$rewards));
				}
			}else
				$reward['id']=0;
			$dti=$ti+$config['orderPayti'];
			$qid='Q'.date("ymd",$ti).sprintf("%06d",$r['id']+1,6);
			$q=$db->prepare("INSERT INTO orders (cid,uid,qid,qid_ti,due_ti,rid,status,ti) VALUES (:cid,:uid,:qid,:qid_ti,:due_ti,:rid,'pending',:ti)");
			$q->execute(array(':cid'=>$ru['id'],':uid'=>$uid,':qid'=>$qid,':qid_ti'=>$ti,':due_ti'=>$dti,':rid'=>$reward['id'],':ti'=>$ti));
			$oid=$db->lastInsertId();
			$s=$db->prepare("SELECT * FROM cart WHERE si=:si");
			$s->execute(array(':si'=>SESSIONID));
			while($r=$s->fetch(PDO::FETCH_ASSOC)){
				$si=$db->prepare("SELECT title,quantity FROM content WHERE id=:id");
				$si->execute(array(':id'=>$r['iid']));
				$i=$si->fetch(PDO::FETCH_ASSOC);
				$quantity=$i['quantity']-$r['quantity'];
				$qry=$db->prepare("UPDATE content SET quantity=:quantity WHERE id=:id");
				$qry->execute(array(':quantity'=>$quantity,':id'=>$r['iid']));
				$sq=$db->prepare("INSERT INTO orderitems (oid,iid,cid,title,quantity,cost,ti) VALUES (:oid,:iid,:cid,:title,:quantity,:cost,:ti)");
				$sq->execute(array(':oid'=>$oid,':iid'=>$r['iid'],':cid'=>$r['cid'],':title'=>$i['title'],':quantity'=>$r['quantity'],':cost'=>$r['cost'],':ti'=>$ti));
			}
			$q=$db->prepare("DELETE FROM cart WHERE si=:si");
			$q->execute(array(':si'=>SESSIONID));
			$config=$db->query("SELECT * FROM config WHERE id='1'")->fetch(PDO::FETCH_ASSOC);
			if($config['email']!=''){
				require'core'.DS.'class.phpmailer.php';
				$mail=new PHPMailer();
				$mail->IsSMTP();
				$mail->SetFrom($config['email'],$config['seoTitle']);
				$mail->AddAddress($config['email']);
				$mail->IsHTML(true);
				$mail->Subject='New Order was Created at '.$config['seoTitle'];
				$msg='New Order was Created at '.$config['seoTitle'].'<br />';
				$msg.='Order #'.$qid;
				$mail->Body=$msg;
				$mail->AltBody=$msg;
				if($mail->Send()){}
			}
			$notification.=$theme['settings']['cart_success'];
		}else
			$notification.=$theme['settings']['cart_suspend'];
		$html=preg_replace('~<emptycart>.*?<\/emptycart>~is',$notification,$html,1);
	}else
		$html=preg_replace('~<emptycart>.*?<\/emptycart>~is','',$html,1);
}else{
	$total=0;
	if(stristr($html,'<items')){
		$s=$db->prepare("SELECT * FROM cart WHERE si=:si ORDER BY ti DESC");
		$s->execute(array(':si'=>SESSIONID));
		preg_match('/<items>([\w\W]*?)<\/items>/',$html,$matches);
		$cartloop=$matches[1];
		$cartitems='';
		if($s->rowCount()>0){
			while($ci=$s->fetch(PDO::FETCH_ASSOC)){
				$cartitem=$cartloop;
				$si=$db->prepare("SELECT * FROM content WHERE id=:id");
				$si->execute(array(':id'=>$ci['iid']));
				$i=$si->fetch(PDO::FETCH_ASSOC);
				$sc=$db->prepare("SELECT * FROM choices WHERE id=:id");
				$sc->execute(array(':id'=>$ci['cid']));
				$c=$sc->fetch(PDO::FETCH_ASSOC);
				$cartitem=str_replace(array(
					'<print content=code>','<print content="code">',
					'<print content=title>','<print content="title">',
					'<print choice>',
					'<print cart=id>','<print cart="id">',
					'<print cart=quantity>','<print cart="quantity">',
					'<print cart=cost>','<print cart="cost">',
					'<print itemscalculate>'
				),array(
					htmlspecialchars($i['code'],ENT_QUOTES,'UTF-8'),htmlspecialchars($i['code'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($i['title'],ENT_QUOTES,'UTF-8'),htmlspecialchars($i['title'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($c['title'],ENT_QUOTES,'UTF-8'),
					$ci['id'],$ci['id'],
					htmlspecialchars($ci['quantity'],ENT_QUOTES,'UTF-8'),htmlspecialchars($ci['quantity'],ENT_QUOTES,'UTF-8'),
					$ci['cost'],$ci['cost'],
					$ci['cost']*$ci['quantity']
				),$cartitem);
				$total=$total+($ci['cost']*$ci['quantity']);
				$cartitems.=$cartitem;
			}
			$html=preg_replace('~<items>.*?<\/items>~is',$cartitems,$html,1);
			$total=$total+$ci['postagecost'];
			$html=str_replace('<print totalcalculate>',$total,$html);
			if(isset($user['id'])&&$user['id']>0)
				$html=preg_replace('~<loggedin>.*?<\/loggedin>~is','<input type="hidden" name="email" value="'.$user['email'].'">',$html,1);
		}else
			$html=preg_replace('~<emptycart>.*?<\/emptycart>~is',$theme['settings']['cart_empty'],$html,1);
	}
}
$content.=$html;
