<?php

namespace DavidMaximous\Fawaterak\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DavidMaximous\Fawaterak\Traits\SetVariables;
use DavidMaximous\Fawaterak\Traits\SetRequiredFields;

class BaseController
{
	use SetVariables,SetRequiredFields;
}
