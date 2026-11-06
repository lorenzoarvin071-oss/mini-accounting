<?php
require __DIR__ . '/includes/bootstrap.php';
if (me()) audit('LOGOUT', me()['username']);
session_destroy();
go('login.php');
