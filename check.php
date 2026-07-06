<?php
echo '<pre>';
echo shell_exec('which php 2>&1') . "\n";
echo shell_exec('php -v 2>&1') . "\n";
echo shell_exec('find /usr/bin /usr/local/bin -name "php*" 2>&1') . "\n";
echo '</pre>';