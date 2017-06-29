<?php
$file = basename(__FILE__);
$city = (strcasecmp(basename(__FILE__, '.php'), 'index') == 0) ? 'Amsterdam|Amstelveen|Hoofddorp' : ucfirst(basename(__FILE__, '.php'));

require_once('include.php');