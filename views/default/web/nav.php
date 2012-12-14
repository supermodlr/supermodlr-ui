<nav>
<a href="/supermodlr/">Models</a>
<?php

if (isset($model_name))
{
	?><a href="/supermodlr/<?php echo $model_name; ?>"><?php echo ucfirst(str_replace('_',' ',$model_name));?></a><?php
}
?>
</nav>