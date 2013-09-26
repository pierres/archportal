<?= get_class($e); ?> 
<?= $e->getMessage(); ?> 

Type: <?= $type; ?> 
File: <?= $e->getFile(); ?> 
Line: <?= $e->getLine(); ?> 

Context:
<?php
foreach ($context as $line => $content) {
	echo ++$line.' '.$content."\n";
}
?>

Trace:
<?= $e->getTraceAsString(); ?> 

Files:
<?= implode("\n", $files); ?> 
