<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Merge these routes before generic application routes. (:any) matches one segment.
$route['internal/v1'] = 'InternalApi';
$route['internal/v1/(.*)'] = 'InternalApi';
