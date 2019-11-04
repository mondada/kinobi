<?php

header("Location: " . (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on" ? "http" : "https") . "://" . $_SERVER['SERVER_NAME'] . "/webadmin");
