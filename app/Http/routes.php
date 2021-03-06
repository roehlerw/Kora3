<?php

Route::get('/', 'WelcomeController@index');
Route::get('/home', 'WelcomeController@index');
Route::post('/language','WelcomeController@setTemporaryLanguage');

//project routes
Route::resource('projects', 'ProjectController');

//project group routes
Route::get('/projects/{pid}/manage/projectgroups', 'ProjectGroupController@index');
Route::post('/projects/{pid}/manage/projectgroups/create', 'ProjectGroupController@create');
Route::patch('projects/{pid}/manage/projectgroups/removeUser', 'ProjectGroupController@removeUser');
Route::patch('projects/{pid}/manage/projectgroups/addUser', 'ProjectGroupController@addUser');
Route::patch('projects/{pid}/manage/projectgroups/updatePermissions', 'ProjectGroupController@updatePermissions');
Route::delete('projects/{pid}/manage/projectgroups/deleteProjectGroup', 'ProjectGroupController@deleteProjectGroup');

//form group routes
Route::get('/projects/{pid}/manage/formgroups', 'FormGroupController@index');
Route::post('/projects/{pid}/manage/formgroups/create', 'FormGroupController@create');
Route::patch('projects/{pid}/manage/formgroups/removeUser', 'FormGroupController@removeUser');
Route::patch('projects/{pid}/manage/formgroups/addUser', 'FormGroupController@addUser');
Route::patch('projects/{pid}/manage/formgroups/updatePermissions', 'FormGroupController@updatePermissions');
Route::delete('projects/{pid}/manage/formgroups/deleteFormGroup', 'FormGroupController@deleteFormGroup');

//admin routes
Route::get('/admin/users', 'AdminController@users');
Route::patch('/admin/update', 'AdminController@update');
Route::patch('/admin/batch', 'AdminController@batch');
Route::delete('admin/deleteUser/{id}', 'AdminController@deleteUser');

//token routes
Route::get('/tokens', 'TokenController@index');
Route::post('/tokens/create', 'TokenController@create');
Route::patch('/tokens/deleteProject', 'TokenController@deleteProject');
Route::patch('/tokens/addProject', 'TokenController@addProject');
Route::delete('/tokens/deleteToken', 'TokenController@deleteToken');

//form routes
Route::get('/projects/{pid}/forms','ProjectController@show'); //alias for project/{id}
Route::patch('/projects/{pid}/forms/{fid}','FormController@update');
Route::get('/projects/{pid}/forms/create','FormController@create');
Route::get('/projects/{pid}/forms/{fid}','FormController@show');
Route::delete('/projects/{pid}/forms/{fid}','FormController@destroy');
Route::get('/projects/{pid}/forms/{fid}/edit','FormController@edit');
Route::post('/projects/{pid}/forms/{fid}/createNode','FormController@addNode');
Route::post('/projects/{pid}/forms/{fid}/deleteNode/{title}','FormController@deleteNode');
Route::post('/projects/{pid}/forms/{fid}/preset', 'FormController@preset');
Route::post('/projects/{pid}','FormController@store');

//field routes
Route::get('/projects/{pid}/forms/{fid}/fields','FormController@show'); //alias for form/{id}
Route::patch('/projects/{pid}/forms/{fid}/fields/{flid}','FieldController@update');
Route::get('/projects/{pid}/forms/{fid}/fields/create','FieldController@create');
Route::get('/projects/{pid}/forms/{fid}/fields/{flid}','FieldController@show');
Route::delete('/projects/{pid}/forms/{fid}/fields/{flid}','FieldController@destroy');
Route::get('/projects/{pid}/forms/{fid}/fields/{flid}/edit','FieldController@edit');
Route::get('/projects/{pid}/forms/{fid}/fields/{flid}/options','FieldController@show'); //alias for fields/{id}
Route::patch('/projects/{pid}/forms/{fid}/fields/{flid}/options/required','FieldController@updateRequired');
Route::patch('/projects/{pid}/forms/{fid}/fields/{flid}/options/default','FieldController@updateDefault');
Route::patch('/projects/{pid}/forms/{fid}/fields/{flid}/options/update','FieldController@updateOptions');
Route::post('/projects/{pid}/forms/{fid}/fields/{flid}/options/saveList','FieldController@saveList');
Route::post('/projects/{pid}/forms/{fid}/fields/{flid}/options/saveDateList','FieldController@saveDateList');
Route::post('/projects/{pid}/forms/{fid}/fields/{flid}/clearRecency', 'FieldController@clearRecency');
Route::post('/projects/{pid}/forms/{fid}/fields/{flid}/checkRecency', 'FieldController@checkRecency');
Route::post('/projects/{pid}/forms/{fid}','FieldController@store');
Route::post('/field/move', 'FieldNavController@index');
Route::post('/saveTmpFile/{flid}', 'FieldController@saveTmpFile');
Route::patch('/saveTmpFile/{flid}', 'FieldController@saveTmpFile');
Route::delete('/deleteTmpFile/{flid}/{filename}', 'FieldController@delTmpFile');
Route::get('/download/{rid}/{flid}/{filename}','FieldController@getFileDownload')->where('filename', '[A-Za-z0-9\-\_\.]+');

//record preset routes
Route::get('/projects/{pid}/forms/{fid}/records/presets', 'RecordPresetController@index');
Route::patch('/changePresetName', 'RecordPresetController@changePresetName');
Route::delete('/deletePreset', 'RecordPresetController@deletePreset');
Route::post('/getRecordArray', 'RecordPresetController@getRecordArray');

//record routes
Route::get('/projects/{pid}/forms/{fid}/records','RecordController@index');
Route::get('projects/{pid}/forms/{fid}/records/massAssignRecords','RecordController@showMassAssignmentView');
Route::post('projects/{pid}/forms/{fid}/records/massAssignRecords','RecordController@massAssignRecords');
Route::patch('/projects/{pid}/forms/{fid}/records/{rid}','RecordController@update');
Route::get('/projects/{pid}/forms/{fid}/records/create','RecordController@create');
Route::get('/projects/{pid}/forms/{fid}/records/{rid}','RecordController@show');
Route::delete('/projects/{pid}/forms/{fid}/records/{rid}','RecordController@destroy');
Route::get('/projects/{pid}/forms/{fid}/records/{rid}/edit','RecordController@edit');
Route::post('/projects/{pid}/forms/{fid}/records','RecordController@store');
Route::delete('projects/{pid}/forms/{fid}/deleteAllRecords','RecordController@deleteAllRecords');
Route::post('/presetRecord', 'RecordController@presetRecord');


//revision routes
Route::get('/projects/{pid}/forms/{fid}/records/revisions/recent', 'RevisionController@index');
Route::get('/projects/{pid}/forms/{fid}/records/revisions/{rid}', 'RevisionController@show');
Route::get('/rollback', 'RevisionController@rollback');

//user routes
Route::get('/user', 'Auth\UserController@index');
Route::get('/user/profile', 'Auth\UserController@index');
Route::patch('/user/changepw', 'Auth\UserController@changepw');
Route::get('/user/activate/{token}', 'Auth\UserController@activate');
Route::get('/auth/activate', 'Auth\UserController@activateshow');
Route::post('/auth/activate', 'Auth\UserController@activator');
Route::post('/user/profile','Auth\UserController@changeprofile');

//metadata routes
Route::get('/projects/{pid}/forms/{fid}/metadata/setup','MetadataController@index');
Route::post('/projects/{pid}/forms/{fid}/metadata/setup','MetadataController@store');
Route::delete('/projects/{pid}/forms/{fid}/metadata/setup','MetadataController@destroy');
Route::get('/projects/{pid}/forms/{fid}/metadata','MetadataController@records');

//install routes
Route::get('/install','InstallController@index');
Route::post('/install','InstallController@install');
Route::get('/install/migrate',"InstallController@runMigrate");

//backup routes
Route::get('/backup','BackupController@index');
Route::post('/backup/start','BackupController@create');
Route::get('/backup/download','BackupController@download');
Route::post('/backup/restore/start','BackupController@restoreData');
Route::post('/backup','BackupController@startBackup');
Route::post('/backup/restore','BackupController@startRestore');
Route::post('/backup/unlock','BackupController@unlockUsers');

Route::controllers([
	'auth' => 'Auth\AuthController',
	'password' => 'Auth\PasswordController',
]);