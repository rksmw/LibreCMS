<h1 class="page-toolbar">
    <div class="pull-right">
        <div class="btn-group"<?php if($config['options']{4}==1)echo' data-toggle="tooltip" data-placement="left" title="Purge All"';?>>
            <button class="btn btn-warning" onclick="purge('0','logs')"><i class="libre libre-purge"></i></button>
        </div>
        <div class="btn-group"<?php if($config['options']{4}==1)echo' data-toggle="tooltip" data-placement="left" title="Show Items"';?>>
            <button class="btn btn-info dropdown-toggle" data-toggle="dropdown"><i class="libre libre-view"></i></button>
            <ul class="dropdown-menu pull-right">
                <li><a href="<?php echo URL.$settings['system']['admin'].'/activity';?>">All</a></li>
<?php $st=$db->query("SELECT DISTINCT action FROM logs ORDER BY action ASC");
while($sr=$st->fetch(PDO::FETCH_ASSOC))
    echo'<li><a href="'.URL.$settings['system']['admin'].'/activity/action/'.$sr['action'].'">'.ucfirst($sr['action']).'</a></li>';?>
            </ul>
        </div>
    </div>
</h1>
<div class="clearfix"></div>
<div class="panel panel-default">
    <div id="l_activity" class="panel-body">
<?php $is=0;
$ie=$config['showItems'];
if(isset($args[1]))
    $action=$args[1];
else
    $action='';
include('core'.DS.'layout'.DS.'activity_items.php');?>
    </div>
</div>
