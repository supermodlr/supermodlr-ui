<?php

?>
<div>
<h1><?php echo ucfirst(str_replace('_',' ',$model_name)); ?></h1>
	<a href="/supermodlr/<?php echo $model_name; ?>/create">Create</a><?php
	if (count($model_rows) > 0)
	{
		?><table><?php
		$c = 0;
		foreach ($model_rows as $row)
		{
			if ($c == 0)
			{ ?><thead><tr><?php
				$col_count = 0;
				foreach ($fields as $col => $val) 
				{
					if ($col_count == 10) continue;
					?><td><?=$col ?></td><?php
					$col_count++;
				} ?></tr></thead><tbody><?php 
			}
			$col_count = 0;
			foreach ($fields as $col => $val) 
			{
				if ($col_count == 10) continue;
				if ($col == '_id') 
				{
					?><td><a href="/supermodlr/<?php echo $model_name; ?>/read/<?=$row['_id']; ?>"><?=$row['_id']; ?></a></td><?php
				}
				else
				{
					?><td><?php if (isset($row[$col]) && is_scalar($row[$col])) { echo $row[$col]; } else if (isset($row[$col])) { echo substr(var_export($row[$col],TRUE),0,25); } ?></td><?php
				}
				$col_count++;
			} ?>

				
				<td><a href="/supermodlr/<?php echo $model_name; ?>/update/<?php echo $row['_id']; ?>">Edit</a></td>
				<td><a href="/supermodlr/<?php echo $model_name; ?>/delete/<?php echo $row['_id']; ?>">Delete</a></td>
			</tr>
			</tbody><?php
			$c++;
		}
		?></tbody></table><?php
	}
	else 
	{
		?><br/>No results<?php
	}
?></div>