<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\Helper;

Auth::logout();
Helper::setFlash('info', 'You have been safely signed out.');
header('Location: index.php');
exit;
