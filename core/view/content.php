<?php
$rank=0;
$notification='';
$show='categories';
$status='published';
$theme=parse_ini_file(THEME.DS.'theme.ini',true);
$itemCount=$config['showItems'];
if($view=='newsletters'){
	if($args[0]=='unsubscribe'&&isset($args[1])){
		$s=$db->prepare("DELETE FROM subscribers WHERE hash=:hash");
		$s->execute(array(':hash'=>$args[1]));
		$notification=$theme['settings']['notification_unsubscribe'];
	}
}
if($view=='page'){
	$show='';
}elseif($view=='search'){
	if(isset($args[0])&&$args[0]!='')
		$search='%'.html_entity_decode(str_replace('-','%',$args[0])).'%';
	elseif(isset($_POST['search'])&&$_POST['search']!='')
		$search='%'.html_entity_decode(str_replace('-','%',filter_input(INPUT_POST,'search',FILTER_SANITIZE_STRING))).'%';
	else
		$search='%';
	$s=$db->prepare("SELECT * FROM content WHERE LOWER(code) LIKE LOWER(:code) OR LOWER(brand) LIKE LOWER(:brand) OR LOWER(title) LIKE LOWER(:title) OR LOWER(category_1) LIKE LOWER(:category_1) OR LOWER(category_2) LIKE LOWER(:category_2) OR LOWER(seoKeywords) LIKE LOWER(:keywords) OR LOWER(tags) LIKE LOWER(:tags) OR LOWER(seoCaption) LIKE LOWER(:caption) OR LOWER(seoDescription) LIKE LOWER(:description) OR LOWER(notes) LIKE LOWER(:notes) AND status=:status ORDER BY ti DESC");
	$s->execute(array(':code'=>$search,':brand'=>$search,':category_1'=>$search,':category_2'=>$search,':title'=>$search,':keywords'=>$search,':tags'=>$search,':caption'=>$search,':description'=>$search,':notes'=>$search,':status'=>$status));
}elseif($view=='index'){
	if(stristr($html,'<settings')){
		preg_match('/<settings items="([\w\W]*?)" contenttype="([\w\W]*?)">/',$html,$matches);
		if(isset($matches[1]))
			$itemCount=$matches[1];
		else
			$itemCount=$config['itemCount'];
		if($itemCount==0)$itemCount=10;
		if(isset($matches[2])){
			$contentType=$matches[2];
			if($contentType=='all'||$contentType=='')$contentType='%';
		}else$contenType='%';
	}else{
		$itemCount=$config['showItems'];
		$contentType='%';
	}
	$s=$db->prepare("SELECT * FROM content WHERE contentType LIKE :contentType AND contentType NOT LIKE 'message%'	AND contentType NOT LIKE 'testimonial%'	AND contentType NOT LIKE 'proof%' AND status LIKE :status AND internal!='1' AND pti < :ti	ORDER BY featured DESC, ti DESC LIMIT $itemCount");
	$s->execute(array(':contentType'=>$contentType,':status'=>$status,':ti'=>time()));
}elseif($view=='bookings'){
	if(isset($args[0]))
		$id=(int)$args[0];
	else
		$id=0;
}elseif(isset($args[1])&&strlen($args[1])==2){
	$s=$db->prepare("SELECT * FROM content WHERE contentType LIKE :contentType AND ti < :ti ORDER BY ti ASC");
	$s->execute(array(':contentType'=>$view,':ti'=>DateTime::createFromFormat('!d/m/Y', '01/'.$args[1].'/'.$args[0])->getTimestamp()));
	$show='categories';
}elseif(isset($args[0])&&strlen($args[0])==4){
	$s=$db->prepare("SELECT * FROM content WHERE contentType LIKE :contentType AND ti>:ti ORDER BY ti ASC");
	$tim=strtotime('01-Jan-'.$args[0]);
	$s->execute(array(':contentType'=>$view,':ti'=>DateTime::createFromFormat('!d/m/Y', '01/01/'.$args[0])->getTimestamp()));
	$show='categories';
}elseif(isset($args[1])){
	$s=$db->prepare("SELECT * FROM content WHERE contentType LIKE :contentType AND LOWER(category_1) LIKE LOWER(:category_1) AND LOWER(category_2) LIKE LOWER(:category_2) AND status LIKE :status AND internal!='1' AND pti < :ti ORDER BY ti DESC");
	$s->execute(array(':contentType'=>$view,':category_1'=>html_entity_decode(str_replace('-',' ',$args[0])),':category_2'=>html_entity_decode(str_replace('-',' ',$args[1])),':status'=>$status,':ti'=>time()));
}elseif(isset($args[0])){
	$s=$db->prepare("SELECT * FROM content WHERE contentType LIKE :contentType AND LOWER(category_1) LIKE LOWER(:category_1) AND status LIKE :status AND internal!='1' AND pti < :ti ORDER BY ti DESC");
	$s->execute(array(':contentType'=>$view,':category_1'=>html_entity_decode(str_replace('-',' ',$args[0])),':status'=>$status,':ti'=>time()));
	if($s->rowCount()<1){
		if($view=='proofs'||$view=='proof'){
			$status='%';
			if($_SESSION['loggedin']==false)die();
		}
		$s=$db->prepare("SELECT * FROM content WHERE contentType LIKE :contentType AND LOWER(title) LIKE LOWER(:title) AND status LIKE :status AND internal!='1' AND pti < :ti ORDER BY ti DESC");
		$s->execute(array(':contentType'=>$view,':title'=>html_entity_decode(str_replace('-',' ',$args[0])),':status'=>$status,':ti'=>time()));
		$show='item';
	}
}else{
	if($view=='proofs'||$view=='proof'){
		if(isset($_SESSION['uid'])&&$_SESSION['uid']!=0){
			$s=$db->prepare("SELECT * FROM content WHERE contentType LIKE 'proof%' AND cid=:cid ORDER BY ti DESC");
			$s->execute(array(':cid'=>$_SESSION['uid']));
		}
	}else{
		$s=$db->prepare("SELECT * FROM content WHERE contentType LIKE :contentType AND status LIKE :status AND internal!='1' AND pti < :ti ORDER BY ti DESC LIMIT $itemCount");
		$s->execute(array(':contentType'=>$view,':status'=>$status,':ti'=>time()));
	}
}
if($show=='categories'){
	$contentType=$view;
	$html=preg_replace('~<item>.*?<\/item>~is','',$html,1);
	if(stristr($html,'<settings')){
		$matches=preg_match_all('/<settings items="(.*?)" contenttype="(.*?)">/',$html,$matches);
		$count=$matches[1];
		$html=preg_replace('~<settings.*?>~is','',$html,1);
	}else$count=1;
	$html=str_replace('<print view>',$view,$html);
	if(stristr($html,'<print page=cover>')||stristr($html,'<print page="cover">')){
		if($page['cover']!=''||$page['coverURL']!=''){
			$cover=basename($page['cover']);
			list($width,$height)=getimagesize($page['cover']);
			if($amp=='/amp')
				$coverHTML='<amp-img src="';
			else
				$coverHTML='<img class="img-responsive" src="';
			if(file_exists('media'.DS.$cover))
				$coverHTML.=$page['cover'];
			elseif($page['coverURL']!='')
				$coverHTML.=$page['coverURL'];
			$coverHTML.='" alt="';
			if($page['attributionImageTitle']==''&&$page['attributionImageName']==''&&$page['attributionImageURL']==''){
				if($page['attributionImageTitle']){
					$coverHTML.=$page['attributionImageTitle'];
					if($page['attributionImageName'])$coverHTML.=' - ';
				}
				if($page['attributionImageName']){
					$coverHTML.=$page['attributionImageName'];
					if($page['attributionImageURL'])$coverHTML.=' - ';
				}
				if($page['attributionImageURL'])$coverHTML.=$page['attributionImageURL'];
			}else{
				if($page['seoTitle'])
					$coverHTML.=$page['seoTitle'];
				else
					$config['seoTitle'];
			}
			if($page['seoTitle']==''&&$config['seoTitle']=='')
				$coverHTML.=basename($page['cover']);
			$coverHTML.='"';
			if($amp=='/amp')
				$coverHTML.=' width="'.$width.'" height="'.$height.'"';
			$coverHTML.='>';
			if($amp=='/amp')
				$coverHTML.='</amp-img>';
		}else
			$coverHTML='';
		$html=str_replace(array('<print page=cover>','<print page="cover">'),$coverHTML,$html);
	}
	$html=str_replace(array('<print page=notes>','<print page="notes">'),rawurldecode($page['notes']),$html);
	if($config['business'])
		$html=str_replace(array('<print content=seoTitle>','<print content="seoTitle">'),htmlspecialchars($config['business'],ENT_QUOTES,'UTF-8'),$html);
	else
		$html=str_replace(array('<print content=seoTitle>','<print content="seoTitle">'),htmlspecialchars($config['seoTitle'],ENT_QUOTES,'UTF-8'),$html);
	$html=str_replace('<notification>',$notification,$html);
	if(stristr($html,'<mediaitems')){
		$sm=$db->prepare("SELECT * FROM media WHERE pid=:pid AND rid=0 ORDER BY ord ASC");
		$sm->execute(array(':pid'=>$page['id']));
		if($sm->rowCount()>0){
			preg_match('/<mediaitems>([\w\W]*?)<\/mediaitems>/',$html,$matches2);
			$media=$matches2[1];
			preg_match('/<mediaimages>([\w\W]*?)<\/mediaimages>/',$media,$matches3);
			$mediaitem=$matches3[1];
			$mediaoutput='';
			while($rm=$sm->fetch(PDO::FETCH_ASSOC)){
				$mediaitems=$mediaitem;
				list($width,$height)=getimagesize($rm['file']);
				$tags='';
				if($rm['tags']!=''){
					$mediatags=explode(',',$rm['tags']);
					foreach($mediatags as $mt){
						$tags.='#'.htmlspecialchars($mt,ENT_QUOTES,'UTF-8').' ';
					}
				}
				$mediaitems=preg_replace(array(
					'/<print media=[\"\']?image[\"\']?>/',
					'/<print media=[\"\']?width[\"\']?>/',
					'/<print media=[\"\']?height[\"\']?>/',
					'/<print media=[\"\']?title[\"\']?>/',
					'/<print media=[\"\']?category_1[\"\']?>/',
					'/<print media=[\"\']?category_2[\"\']?>/',
					'/<print media=[\"\']?attributionName[\"\']?>/',
					'/<print media=[\"\']?attributionURL[\"\']?>/',
					'/<print media=[\"\']?exifISO[\"\']?>/',
					'/<print media=[\"\']?exifAperture[\"\']?>/',
					'/<print media=[\"\']?exifFocalLength[\"\']?>/',
					'/<print media=[\"\']?exifShutterSpeed[\"\']?>/',
					'/<print media=[\"\']?exifCamera[\"\']?>/',
					'/<print media=[\"\']?exifLens[\"\']?>/',
					'/<print media=[\"\']?exifFilename[\"\']?>/',
					'/<print media=[\"\']?exifTime[\"\']?>/',
					'/<print media=[\"\']?tags[\"\']?>/',
					'/<print media=[\"\']?seoTitle[\"\']?>/',
					'/<print media=[\"\']?caption[\"\']?>/',
					'/<print media=[\"\']?description[\"\']?>/'
				),array(
					htmlspecialchars($rm['file'],ENT_QUOTES,'UTF-8'),
					$width,
					$height,
					htmlspecialchars($rm['title'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['category_1'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['category_2'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['attributionImageName'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['attributionImageURL'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['exifISO'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['exifAperture'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['exifFocalLength'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['exifShutterSpeed'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['exifCamera'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['exifLens'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['exifFilename'],ENT_QUOTES,'UTF-8'),
					date($config['dateFormat'],$rm['exifti']),
					$tags,
					htmlspecialchars($rm['seoTitle'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['seoCaption'],ENT_QUOTES,'UTF-8'),
					htmlspecialchars($rm['seoDescription'],ENT_QUOTES,'UTF-8')
				),$mediaitems);
				$mediaoutput.=$mediaitems;
			}
			$html=preg_replace('~<mediaimages>.*?<\/mediaimages>~is',$mediaoutput,$html,1);
		}else
			$html=preg_replace('~<mediaitems>.*?<\/mediaitems>~is','',$html,1);
	}
	if(stristr($html,'<items>')){
		preg_match('/<items>([\w\W]*?)<\/items>/',$html,$matches);
		$item=$matches[1];
		$output='';
		$si=1;
		while($r=$s->fetch(PDO::FETCH_ASSOC)){
			if($view=='search'){
				if($r['contentType']=='testimonials'||$r['contentType']=='proofs')continue;
			}
			$sr=$db->prepare("SELECT active FROM menu WHERE contentType=:contentType");
			$sr->execute(array(':contentType'=>$r['contentType']));
			$pr=$sr->fetch(PDO::FETCH_ASSOC);
			if($pr['active']!=1)continue;
			if($r['status']!=$status)continue;
			$items=$item;
			$contentType=$r['contentType'];
			if($si==1){
				$filechk=basename($r['file']);
				$thumbchk=basename($r['thumb']);
				if($r['file']!=''&&file_exists('media'.DS.$filechk)){
					$shareImage=$r['file'];
					$si++;
				}elseif($r['thumb']!=''&&file_exists('media'.DS.$thumbchk)){
					$shareImage=$r['thumb'];
					$si++;
				}
			}
			$thumb='';
			if($r['thumb'])
				$thumb=str_replace(URL,'',$r['thumb']);
			else
				$thumb=NOIMAGE;
			$items=preg_replace(array(
				'/<print content=[\"\']?thumb[\"\']?>/',
				'/<print content=[\"\']?title[\"\']?>/',
				'/<print author=[\"\']?name[\"\']?>/',
				'/<print content=[\"\']?datePublished[\"\']?>/',
				'/<print content=[\"\']?dateEdited[\"\']?>/',
				'/<print content=[\"\']?contentType[\"\']?>/',
				'/<print content=[\"\']?alttitle[\"\']?>/'
			),array(
				$thumb,
				htmlspecialchars($r['title'],ENT_QUOTES,'UTF-8'),
				htmlspecialchars($r['name'],ENT_QUOTES,'UTF-8'),
				date('Y-m-d',$r['pti']),
				date('Y-m-d',$r['eti']),
				$r['contentType'],
				htmlspecialchars($r['title'],ENT_QUOTES,'UTF-8')
			),$items);
			$r['notes']=strip_tags($r['notes']);
			if($r['contentType']=='testimonials'||$r['contentType']=='testimonial'){
				if(stristr($items,'<controls>'))
				$items=preg_replace('~<controls>.*?<\/controls>~is','',$items,1);
				$controls='';
			}else{
				if(stristr($items,'<view>')){
					$items=str_replace(array(
						'<print content=linktitle>','<print content="linktitle">',
						'<print content=title','<print content="title">',
						'<view>','</view>'
					),array(
						URL.$r['contentType'].'/'.url_encode($r['title']),URL.$r['contentType'].'/'.url_encode($r['title']),
						htmlspecialchars($r['title'],ENT_QUOTES,'UTF-8'),htmlspecialchars($r['title'],ENT_QUOTES,'UTF-8'),
						'',''
					),$items);
				}
				if($r['contentType']=='service'||$r['contentType']=='events'){
					if($r['bookable']==1){
						if(stristr($items,'<service')){
							$items=str_replace(array('<print content=bookservice>','<print content="bookservice">'),$r['id'],$items);
							$items=str_replace(array('<service>','</service>'),'',$items);
							$items=preg_replace('~<inventory>.*?<\/inventory>~is','',$items,1);
						}
					}else
						$items=preg_replace('~<service.*?>.*?<\/service>~is','',$items,1);
				}else
					$items=preg_replace('~<service>.*?<\/service>~is','',$items,1);
				if($r['contentType']=='inventory'&&is_numeric($r['cost'])){
					if(stristr($items,'<inventory')){
						$items=str_replace(array('<inventory>','</inventory>'),'',$items);
						$items=preg_replace('~<service>.*?<\/service>~is','',$items,1);
					}elseif(stristr($items,'<inventory')&&$r['contentType']!='inventory'&&!is_numeric($r['cost']))
						$items=preg_replace('~<inventory>.*?<\/inventory>~is','',$items,1);
				}else
					$items=preg_replace('~<inventory>.*?<\/inventory>~is','',$items,1);
				$items=str_replace(array('<controls>','</controls>'),'',$items);
			}
			require'core'.DS.'parser.php';
			$output.=$items;
		}
		$html=preg_replace('~<items>.*?<\/items>~is',$output,$html,1);
		$html=preg_replace('~<item>.*?<\/item>~is','',$html,1);
	}else
		$html=preg_replace('~<items>.*?<\/items>~is','',$html,1);
	$html=preg_replace('~<item>.*?<\/item>~is','',$html,1);
	$html=str_replace(array('<items>','</items>'),'',$html);
	if(stristr($html,'<more>')){
		if($s->rowCount()<=$config['showItems'])
			$html=preg_replace('~<more>.*?<\/more>~is','',$html,1);
		else{
			$html=str_replace(array(
				'<more>','</more>',
				'<print view>',
				'<print contentType>',
				'<print config=showItems>','<print config="showItems">'
			),array(
				'','',
				$view,
				$contentType,
				$config['showItems'],$config['showItems']
			),$html);
		}
	}
}
if($view=='testimonials')$show='';
if($show=='item'){
	$html=preg_replace('~<items>.*?<\/items>~is','',$html,1);
	$r=$s->fetch(PDO::FETCH_ASSOC);
	$su=$db->prepare("UPDATE content SET views=:views WHERE id=:id");
	$su->execute(array(':views'=>$r['views']+1,':id'=>$r['id']));
	if($r['file']!='')$shareImage=$r['file'];elseif($r['thumb']!='')$shareImage=$r['thumb'];
	$r['seoTitle']=trim($r['seoTitle']);
	$r['title']=empty($r['title'])?'':$r['title'];
	$seoTitle=!empty($r['seoTitle'])?trim(htmlspecialchars($r['seoTitle'],ENT_QUOTES,'UTF-8')):htmlspecialchars($r['title'],ENT_QUOTES,'UTF-8');
	$metaRobots=!empty($r['metaRobots'])?htmlspecialchars($r['metaRobots'],ENT_QUOTES,'UTF-8'):'index,follow';
	$seoCaption=!empty($r['seoCaption'])?htmlspecialchars($r['seoCaption'],ENT_QUOTES,'UTF-8'):htmlspecialchars($page['seoCaption'],ENT_QUOTES,'UTF-8');
	$seoCaption=empty($seoCaption)?htmlspecialchars($config['seoCaption'],ENT_QUOTES,'UTF-8'):$seoCaption;
	$seoDescription=!empty($r['seoDescription'])?htmlspecialchars($r['seoDescription'],ENT_QUOTES,'UTF-8'):htmlspecialchars($page['seoDescription'],ENT_QUOTES,'UTF-8');
	$seoDescription=empty($seoDescrption)?htmlspecialchars($config['seoDescription'],ENT_QUOTES,'UTF-8'):$seoDescription;
	$seoKeywords=!empty($r['seoKeywods'])?htmlspecialchars($r['seoKeywords'],ENT_QUOTES,'UTF-8'):htmlspecialchars($page['seoKeywords'],ENT_QUOTES,'UTF-8');
	$seoKeywords=empty($seoKeywords)?htmlspecialchars($config['seoKeywords'],ENT_QUOTES,'UTF-8'):$seoKeywords;
	$canonical=URL.$view.'/'.url_encode($r['title']);
	if(isset($r['eti'])&&($r['eti']>$r['ti']))
		$contentTime=$r['eti'];
	else
		$contentTime=isset($r['ti'])?$r['ti']:0;
	if(stristr($html,'<print page=cover>')||stristr($html,'<print page="cover">')){
		if($r['file'])
			if($amp=='/amp'){
				list($width,$height)=getimagesize($r['file']);
				$html=str_replace(array('<print page=cover>','<print page="cover">'),'<amp-img src="'.$r['file'].'" alt="'.$r['title'].'" width="'.$width.'" height="'.$height.'"></amp-img>',$html);
			}else
				$html=str_replace(array('<print page=cover>','<print page="cover">'),'<img class="img-responsive" src="'.$r['file'].'" alt="'.$r['title'].'" role="image">',$html);
		elseif($r['fileURL'])
			if($amp=='/amp'){
				list($width,$height)=getimagesize($r['fileURL']);
				$html=str_replace(array('<print page=cover>','<print page="cover">'),'<amp-img src="'.$r['fileURL'].'" alt="'.$r['title'].'" width="'.$width.'" height="'.$height.'"></amp-img>',$html);
			}else
				$html=str_replace(array('<print page=cover>','<print page="cover">'),'<img class="img-responsive" src="'.$r['fileURL'].'" alt="'.$r['title'].'" role="image">',$html);
		elseif($page['cover'])
			if($amp=='/amp'){
				list($width,$height)=getimagesize($page['cover']);
				$html=str_replace(array('<print page=cover>','<print page="cover">'),'<amp-img src="'.$page['cover'].'" alt="'.$r['title'].'" width="'.$width.'" height="'.$height.'"></amp-img>',$html);
			}else
				$html=str_replace(array('<print page=cover>','<print page="cover">'),'<img src="'.$page['cover'].'" alt="'.$r['title'].'" role="image">',$html);
		elseif($page['coverURL'])
			if($amp=='/amp'){
				list($width,$height)=getimagesize($page['coverURL']);
				$html=str_replace(array('<print page=cover>','<print page="cover">'),'<amp-img src="'.$page['coverURL'].'" alt="'.$r['title'].'" width="'.$width.'" height="'.$height.'"></amp-img>',$html);
			}else
				$html=str_replace(array('<print page=cover>','<print page="cover">'),'<img src="'.$page['coverURL'].'" alt="'.$r['title'].'" role="image">',$html);
		else
			$html=str_replace(array('<print page=cover>','<print page="cover">'),'',$html);
	}
	if(stristr($html,'<item')){
		preg_match('/<item>([\w\W]*?)<\/item>/',$html,$matches);
		$item=$matches[1];
		if(stristr($item,'<mediaitems')){
			$sm=$db->prepare("SELECT * FROM media WHERE pid=0 AND rid=:rid ORDER BY ord ASC");
			$sm->execute(array(':rid'=>isset($r['id'])?$r['id']:$page['id']));
			if($sm->rowCount()>0){
				preg_match('/<mediaitems>([\w\W]*?)<\/mediaitems>/',$item,$matches2);
				$media=$matches2[1];
				preg_match('/<mediaimages>([\w\W]*?)<\/mediaimages>/',$media,$matches3);
				$mediaitem=$matches3[1];
				$mediaoutput='';
				while($rm=$sm->fetch(PDO::FETCH_ASSOC)){
					$mediaitems=$mediaitem;
					list($width,$height)=getimagesize($rm['file']);
					$tags='';
					if($rm['tags']!=''){
						$mediatags=explode(',',$rm['tags']);
						foreach($mediatags as $mt){
							$tags.='#'.htmlspecialchars($mt,ENT_QUOTES,'UTF-8').' ';
						}
					}
					$mediaitems=preg_replace(array(
						'/<print media=[\"\']?image[\"\']?>/',
						'/<print media=[\"\']?width[\"\']?>/',
						'/<print media=[\"\']?height[\"\']?>/',
						'/<print media=[\"\']?title[\"\']?>/',
						'/<print media=[\"\']?category_1[\"\']?>/',
						'/<print media=[\"\']?category_2[\"\']?>/',
						'/<print media=[\"\']?attributionName[\"\']?>/',
						'/<print media=[\"\']?attributionURL[\"\']?>/',
						'/<print media=[\"\']?exifISO[\"\']?>/',
						'/<print media=[\"\']?exifAperture[\"\']?>/',
						'/<print media=[\"\']?exifFocalLength[\"\']?>/',
						'/<print media=[\"\']?exifShutterSpeed[\"\']?>/',
						'/<print media=[\"\']?exifCamera[\"\']?>/',
						'/<print media=[\"\']?exifLens[\"\']?>/',
						'/<print media=[\"\']?exifFilename[\"\']?>/',
						'/<print media=[\"\']?exifTime[\"\']?>/',
						'/<print media=[\"\']?tags[\"\']?>/',
						'/<print media=[\"\']?seoTitle[\"\']?>/',
						'/<print media=[\"\']?caption[\"\']?>/',
						'/<print media=[\"\']?description[\"\']?>/'
					),array(
						$rm['file'],
						$width,
						$height,
						htmlspecialchars($rm['title'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['category_1'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['category_2'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['attributionImageName'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['attributionImageURL'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['exifISO'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['exifAperture'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['exifFocalLength'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['exifShutterSpeed'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['exifCamera'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['exifLens'],ENT_QUOTES,'UTF-8'),
						$rm['exifFilename'],
						date($config['dateFormat'],$rm['exifti']),
						$tags,
						htmlspecialchars($rm['seoTitle'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['seoCaption'],ENT_QUOTES,'UTF-8'),
						htmlspecialchars($rm['seoDescription'],ENT_QUOTES,'UTF-8')
					),$mediaitems);
					$mediaoutput.=$mediaitems;
				}
				$item=str_replace(array('<mediaitems>','</mediaitems>'),'',$item);
				$item=preg_replace('~<mediaimages>.*?<\/mediaimages>~is',$mediaoutput,$item,1);
			}else
				$item=preg_replace('~<mediaitems>.*?<\/mediaitems>~is','',$item,1);
		}else
			$item=preg_replace('~<mediaitems>.*?<\/mediaitems>~is','',$item,1);
		if(isset($r['contentType'])&&($r['contentType']=='service'||$r['contentType']=='events')){
			if($r['bookable']==1){
				if(stristr($item,'<service>')){
					$item=str_replace(array('<print content=bookservice>','<print content="bookservice">'),$r['id'],$item);
					$item=str_replace(array('<service>','</service>'),'',$item);
					$item=preg_replace('~<inventory>.*?<\/inventory>~is','',$item,1);
				}
			}else
				$item=preg_replace('~<service.*?>.*?<\/service>~is','',$item,1);
		}else
			$item=preg_replace('~<service>.*?<\/service>~is','',$item,1);
		$address='';
		$edit='';
		$contentQuantity='';
		if(isset($r['contentType'])&&($r['contentType']=='inventory')){
			if(is_numeric($r['quantity'])&&$r['quantity']!=0){
				$contentQuantity='<link itemprop="availability" href="http://schema.org/InStock">';
				$contentQuantity.='<div class="quantity">Quantity<br>'.htmlspecialchars($r['quantity'],ENT_QUOTES,'UTF-8').'</div>';
			}elseif(is_numeric($r['quantity'])&&$r['quantity']==0){
				$contentQuantity='<link itemprop="availability" href="http://schema.org/OutOfStock">';
				$contentQuantity.='<div class="quantity">Out of Stock</div>';
			}else$contentQuantity.='<div class="quantity">Quantity<br>'.htmlspecialchars($r['quantity'],ENT_QUOTES,'UTF-8').'</div>';
			$item=str_replace(array('<print content=quantity>','<print content="quantity">'),$contentQuantity,$item);
		}else
			$item=str_replace(array('<print content=quantity>','<print content="quantity">'),'',$item);
		if(stristr($item,'<choices>')){
			$scq=$db->prepare("SELECT * FROM choices WHERE rid=:id ORDER BY title ASC");
			$scq->execute(array(':id'=>isset($r['id'])?$r['id']:$page['id']));
			if($scq->rowCount()>0){
				$choices='<select class="choices form-control" onchange="$(\'.addCart\').data(\'cartchoice\',$(this).val());$(\'.choices\').val($(this).val());"><option value="0">Select an Option</option>';
				while($rcq=$scq->fetch(PDO::FETCH_ASSOC)){
					if($rcq['ti']==0)continue;
					$choices.='<option value="'.$rcq['id'].'">'.$rcq['title'].':'.$rcq['ti'].'</option>';
				}
				$choices.='</select>';
				$item=str_replace('<choices>',$choices,$item);
			}else
				$item=str_replace('<choices>','',$item);
		}else
			$item=str_replace('<choices>','',$item);
		if(stristr($item,'<json-ld>')){
			$r['schemaType']=isset($r['schemaType'])?$r['schemaType']:$page['schemaType'];
			$r['notes']=isset($r['notes'])?$r['notes']:$page['notes'];
			$r['business']=isset($r['business'])?$r['business']:$config['business'];
			$r['pti']=isset($r['pti'])?$r['pti']:$page['ti'];
			$r['ti']=isset($r['ti'])?$r['ti']:$page['ti'];
			$r['eti']=isset($r['eti'])?$r['eti']:$page['eti'];
			$jsonld='<script type="application/ld+json">{"@context":"http://schema.org/","@type":"'.$r['schemaType'].'","headline":"'.$r['title'].'","alternativeHeadline":"'.$r['title'].'","image":"';
			if(isset($r['thumb'])&&$r['thumb']!='')
				$jsonld.=$r['thumb'];
			elseif(isset($r['file'])&&$r['file']!='')
				$jsonld.=$r['file'];
			if(isset($r['author']))$jsonld.='","author":"'.$r['name'].'"';
			if(isset($r['category_1'])){
				$jsonld.='","genre":"'.$r['category_1'];
				if($r['category_2']!='')
					$jsonld.=" > ".$r['category_2'];
			}
			$jsonld.='","keywords":"'.$seoKeywords.'","wordcount":"'.strlen(strip_tags($r['notes'])).'","publisher":"'.$r['business'].'","url":"'.URL.$view.'/'.'","datePublished":"'.date('Y-m-d',$r['pti']).'","dateCreated":"'.date('Y-m-d',$r['ti']).'","dateModified":"'.date('Y-m-d',$r['eti']).'","description":"'.strip_tags(rawurldecode($seoDescription)).'","articleBody":"'.strip_tags(escaper($r['notes'])).'"}</script>';
			$item=str_replace('<json-ld>',$jsonld,$item);
		}
		if($view!='page'&&stristr($item,'<review>')){
			preg_match('/<review>([\w\W]*?)<\/review>/',$item,$matches);
			$review=$matches[1];
			$sr=$db->prepare("SELECT * FROM comments WHERE contentType='review' AND status='approved' AND rid=:rid");
			$sr->execute(array(':rid'=>isset($r['id'])?$r['id']:$page['id']));
			$reviews='';
			while($rr=$sr->fetch(PDO::FETCH_ASSOC)){
				$reviewitem=$review;
				if(stristr($reviewitem,'<json-ld-review>')){
					$jsonldreview='<script type="application/ld+json">{"@context":"http://schema.org","@type":"Review","author":"'.$rr['name'].'","datePublished":"'.date('Y-m-d',$rr['ti']).'","description":"'.strip_tags(rawurldecode($r['notes'])).'","name":"'.$rr['name'].'","reviewRating":{"@type":"Rating","bestRating":"5","ratingValue":"'.$rr['cid'].'","worstRating":"1"}}</script>';
					$reviewitem=str_replace('<json-ld-review>',$jsonldreview,$reviewitem);
				}
				$reviewitem=str_replace('<print review=rating>',$rr['cid'],$reviewitem);
				$rset=['','','','','',''];
				$rset[$rr['cid']]='set';
				$reviewitem=str_replace(array(
					'<print review=set5>',
					'<print review=set4>',
					'<print review=set3>',
					'<print review=set2>',
					'<print review=set1>',
					'<print review="name">','<print review="name"',
					'<print review=dateAtom>','<print review="dateAtom">',
					'<print review=datetime>','<print review="datetime">',
					'<print review=date>','<print review="date">',
					'<print review=review>','<print review="review">',
				),array(
					$rset[5],
					$rset[4],
					$rset[3],
					$rset[2],
					$rset[1],
					htmlspecialchars($rr['name'],ENT_QUOTES,'UTF-8'),htmlspecialchars($rr['name'],ENT_QUOTES,'UTF-8'),
					date('Y-m-d',$rr['ti']),date('Y-m-d',$rr['ti']),
					date('Y-m-d H:i:s',$rr['ti']),date('Y-m-d H:i:s',$rr['ti']),
					date($config['dateFormat'],$rr['ti']),date($config['dateFormat'],$rr['ti']),
					htmlspecialchars(strip_tags($rr['notes']),ENT_QUOTES,'UTF-8'),htmlspecialchars(strip_tags($rr['notes']),ENT_QUOTES,'UTF-8')
				),$reviewitem);
				$reviews.=$reviewitem;
			}
			$item=preg_replace('~<review>.*?<\/review>~is',$reviews,$item,1);
		}
		require'core'.DS.'parser.php';
		$authorHTML='';
		$seoTitle=$r['title'].' - '.$config['seoTitle'];
		if(isset($r['contentType'])&&($r['contentType']=='article'||$r['contentType']=='portfolio'))
			$item=preg_replace('~<controls>.*?<\/controls>~is','',$item,1);
		$html=preg_replace('~<settings.*?>~is','',$html,1);
		$html=preg_replace('~<items>.*?<\/items>~is','',$html,1);
		$html=preg_replace('~<more>.*?<\/more>~is','',$html,1);
		$html=str_replace(array('<print page=notes>','<print page="notes">'),'',$html);
		if($view=='article'||$view=='events'||$view=='news'||$view=='proofs'){
			if(file_exists(THEME.$amp.DS.'comments.html')){
				$comments='';
				$commentsHTML='';
				$sc=$db->prepare("SELECT * FROM comments WHERE contentType=:contentType AND rid=:rid AND status!='unapproved' ORDER BY ti ASC");
				$sc->execute(array(':contentType'=>$view,':rid'=>$r['id']));
				$commentsHTML=file_get_contents(THEME.$amp.DS.'comments.html');
				$commentsHTML=str_replace(array('<print content=id>','<print content="id">'),$r['id'],$commentsHTML);
				$commentsHTML=str_replace(array('<print content=contentType>','<print content="contentType">'),$r['contentType'],$commentsHTML);
				$commentDOC=new DOMDocument();
				@$commentDOC->loadHTML($commentsHTML);
				preg_match('/<items>([\w\W]*?)<\/items>/',$commentsHTML,$matches);
				while($rc=$sc->fetch(PDO::FETCH_ASSOC)){
					$comment=$matches[1];
					$rc['notes']=htmlspecialchars(strip_tags(rawurldecode($rc['notes'])),ENT_QUOTES,'UTF-8');
					require'core'.DS.'parser.php';
					$comments.=$comment;
				}
				$commentsHTML=preg_replace('~<items>.*?<\/items>~is',$comments,$commentsHTML,1);
				if($r['options']{1}==1){
					$commentsHTML=str_replace(array('<comment>','</comment>'),'',$commentsHTML);
				}else
					$commentsHTML=preg_replace('~<comment>.*?<\/comment>~is','',$commentsHTML,1);
				$commentsHTML=preg_replace('~<items>.*?<\/items>~is','',$commentsHTML,1);
				$item.=$commentsHTML;
			}else
				$item.='Comments for this post is Enabled, but no <strong>"'.THEME.$amp.DS.'comments.html"</strong> template file exists';
		}
		$html=preg_replace('~<item>.*?<\/item>~is',$item,$html,1);
	}
}
$content.=$html;
