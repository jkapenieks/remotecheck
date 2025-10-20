<?php
// English strings
$string['pluginname'] = 'Remote Check';
$string['enabled'] = 'Enable Remote Check for this assignment';
$string['formula'] = 'Remote Check Formula (use p1..p9)';
$string['tolerance'] = 'Tolerance';
$string['tolabs'] = 'Absolute tolerance';
$string['tolpct'] = 'Percentage tolerance (%)';
$string['compare_remote_result'] = 'Also compare to remote "Calculation Result" field';
$string['building'] = 'Name';
$string['paramn'] = 'Param {$a}';
$string['result'] = 'Calculation result';
$string['valid'] = 'Valid';
$string['invalid'] = 'Invalid';
$string['validity'] = 'Validation';
$string['paramchecks'] = 'Parameter checks';
$string['ok'] = 'OK';
$string['mismatch'] = 'Mismatch';
$string['summary'] = 'Remote check: {$a}';
$string['nosubmission'] = 'No remote check submission.';
$string['remotedb'] = 'Remote MySQL data source';
$string['dbhost'] = 'Host';
$string['dbport'] = 'Port';
$string['dbname'] = 'Database name';
$string['dbuser'] = 'Username (read-only)';
$string['dbpass'] = 'Password';
$string['remotecols'] = 'Remote table and columns';
$string['table'] = 'Table name';
$string['idcol'] = 'ID column';
$string['addresscol'] = 'Name column';
$string['paramcol'] = 'Param {$a} column';
$string['resultcol'] = 'Calculation Result column';
$string['cachettl'] = 'Cache TTL (seconds)';
$string['cachettl_desc'] = 'How long to cache remote rows and address lists in Moodle cache.';
$string['remotedbnotready'] = 'Remote data source is not configured or unavailable.';
$string['formula_help'] = 'Enter a mathematical expression using p1..p9, e.g., (p1+p2)/p3. Operators allowed: + - * / ^ and parentheses.
Examples: 
(p1 + p2) / p3
p1*p2 + p3*(p4 - p5)
(p1 + p2 + p3)^2 / p4
';
$string['formulaerror'] = 'Formula error';


$string['paramlabels'] = 'Custom parameter labels';
$string['paramlabels_help'] = 'Override the default captions shown to students for Param 1..9 and the Result field.';
$string['labeln'] = 'Label for Param {$a}';
$string['resultlabel'] = 'Result label';
$string['paramlabel'] = 'Parameters for Remote Check plugin'; // NEW: For the table header
$string['managebuildingdata'] = 'Manage remote building data';
$string['remotecheck:viewvalidation'] = 'View remote check validation details';
$string['remotecheck:managedata']     = 'Manage remote building data';
$string['id'] = 'ID';


$string['table'] = 'Table name';
$string['table_help'] = 'Name of the remote table to use for this assignment. If left blank, the site-level default is used.';
