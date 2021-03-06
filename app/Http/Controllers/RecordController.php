<?php namespace App\Http\Controllers;

use App\DateField;
use App\DocumentsField;
use App\GalleryField;
use App\GeneratedListField;
use App\RecordPreset;
use App\GeolocatorField;
use App\ScheduleField;
use App\User;
use App\Form;
use App\Field;
use App\Record;
use App\TextField;
use App\NumberField;
use App\Http\Requests;
use App\RichTextField;
use App\ListField;
use App\MultiSelectListField;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\FieldHelpers\FieldValidation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;


class RecordController extends Controller {

    /**
     * User must be logged in to access views in this controller.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('active');
    }


	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index($pid, $fid)
	{
        if(!RecordController::checkPermissions($fid)) {
            return redirect('projects/'.$pid.'/forms/'.$fid);
        }

        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);

        return view('records.index', compact('form'));
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create($pid, $fid)
	{
        if(!RecordController::checkPermissions($fid, 'ingest')) {
            return redirect('projects/'.$pid.'/forms/'.$fid);
        }

        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);
        $presets = array();

        foreach(RecordPreset::where('fid', '=', $fid)->get() as $preset)
            $presets[] = ['id' => $preset->id, 'name' => $preset->name];

        $fields = array(); //array of field ids
        foreach($form->fields()->get() as $field)
            $fields[] = $field->flid;

        return view('records.create', compact('form', 'presets', 'fields'));
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store($pid, $fid, Request $request)
	{
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        foreach($request->all() as $key => $value){
            if(!is_numeric($key)){
                continue;
            }
            $message = FieldValidation::validateField($key, $value, $request);
            if($message != ''){
                flash()->error($message);

                return redirect()->back()->withInput();
            }
        }

        $record = new Record();
        $record->pid = $pid;
        $record->fid = $fid;
        $record->owner = $request->userId;
        $record->save(); //need to save to create rid needed to make kid
        $record->kid = $pid.'-'.$fid.'-'.$record->rid;
        $record->save();

        foreach($request->all() as $key => $value){
            if(!is_numeric($key)){
                continue;
            }
            $field = FieldController::getField($key);
            if($field->type=='Text'){
                $tf = new TextField();
                $tf->flid = $field->flid;
                $tf->rid = $record->rid;
                $tf->text = $value;
                $tf->save();
            } else if($field->type=='Rich Text'){
                $rtf = new RichTextField();
                $rtf->flid = $field->flid;
                $rtf->rid = $record->rid;
                $rtf->rawtext = $value;
                $rtf->save();
            } else if($field->type=='Number'){
                $nf = new NumberField();
                $nf->flid = $field->flid;
                $nf->rid = $record->rid;
                $nf->number = $value;
                $nf->save();
            } else if($field->type=='List'){
                $lf = new ListField();
                $lf->flid = $field->flid;
                $lf->rid = $record->rid;
                $lf->option = $value;
                $lf->save();
            } else if($field->type=='Multi-Select List'){
                $mslf = new MultiSelectListField();
                $mslf->flid = $field->flid;
                $mslf->rid = $record->rid;
                $mslf->options = FieldController::msListArrayToString($value);
                $mslf->save();
            } else if($field->type=='Generated List'){
                $glf = new GeneratedListField();
                $glf->flid = $field->flid;
                $glf->rid = $record->rid;
                $glf->options = FieldController::msListArrayToString($value);
                $glf->save();
            } else if($field->type=='Date' && $request->input('year_'.$field->flid)!=''){
                $df = new DateField();
                $df->flid = $field->flid;
                $df->rid = $record->rid;
                $df->circa = $request->input('circa_'.$field->flid, '');
                $df->month = $request->input('month_'.$field->flid);
                $df->day = $request->input('day_'.$field->flid);
                $df->year = $request->input('year_'.$field->flid);
                $df->era = $request->input('era_'.$field->flid, 'CE');
                $df->save();
            } else if($field->type=='Schedule'){
                $sf = new ScheduleField();
                $sf->flid = $field->flid;
                $sf->rid = $record->rid;
                $sf->events = FieldController::msListArrayToString($value);
                $sf->save();
            } else if($field->type=='Geolocator'){
                $gf = new GeolocatorField();
                $gf->flid = $field->flid;
                $gf->rid = $record->rid;
                $gf->locations = FieldController::msListArrayToString($value);
                $gf->save();
            } else if($field->type=='Documents' && glob(env('BASE_PATH').'storage/app/tmpFiles/'.$value.'/*.*') != false){
                $df = new DocumentsField();
                $df->flid = $field->flid;
                $df->rid = $record->rid;
                $infoString = '';
                $newPath = env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid;
                mkdir($newPath,0775,true);
                if(file_exists(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value)) {
                    $types = FieldController::getMimeTypes();
                    foreach (new \DirectoryIterator(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value) as $file) {
                        if ($file->isFile()) {
                            if(!array_key_exists($file->getExtension(),$types))
                                $type = 'application/octet-stream';
                            else
                                $type =  $types[$file->getExtension()];
                            $info = '[Name]' . $file->getFilename() . '[Name][Size]' . $file->getSize() . '[Size][Type]' . $type . '[Type]';
                            if ($infoString == '') {
                                $infoString = $info;
                            } else {
                                $infoString .= '[!]' . $info;
                            }
                            rename(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value . '/' . $file->getFilename(),
                                $newPath . '/' . $file->getFilename());
                        }
                    }
                }
                $df->documents = $infoString;
                $df->save();
            } else if($field->type=='Gallery' && glob(env('BASE_PATH').'storage/app/tmpFiles/'.$value.'/*.*') != false){
                $gf = new GalleryField();
                $gf->flid = $field->flid;
                $gf->rid = $record->rid;
                $infoString = '';
                $newPath = env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid;
                //make the three directories
                mkdir($newPath,0775,true);
                mkdir($newPath.'/thumbnail',0775,true);
                mkdir($newPath.'/medium',0775,true);
                if(file_exists(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value)) {
                    $types = FieldController::getMimeTypes();
                    foreach (new \DirectoryIterator(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value) as $file) {
                        if ($file->isFile()) {
                            if(!array_key_exists($file->getExtension(),$types))
                                $type = 'application/octet-stream';
                            else
                                $type =  $types[$file->getExtension()];
                            $info = '[Name]' . $file->getFilename() . '[Name][Size]' . $file->getSize() . '[Size][Type]' . $type . '[Type]';
                            if ($infoString == '') {
                                $infoString = $info;
                            } else {
                                $infoString .= '[!]' . $info;
                            }
                            rename(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value . '/' . $file->getFilename(),
                                $newPath . '/' . $file->getFilename());
                            rename(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value . '/thumbnail/' . $file->getFilename(),
                                $newPath . '/thumbnail/' . $file->getFilename());
                            rename(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value . '/medium/' . $file->getFilename(),
                                $newPath . '/medium/' . $file->getFilename());
                        }
                    }
                }
                $gf->images = $infoString;
                $gf->save();
            }
        }

        RevisionController::storeRevision($record->rid, 'create');

        flash()->overlay('Your record has been successfully created!', 'Good Job!');

        return redirect('projects/'.$pid.'/forms/'.$fid.'/records');
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($pid, $fid, $rid)
	{
        if(!RecordController::checkPermissions($fid)) {
            return redirect('projects/'.$pid.'/forms/'.$fid);
        }

        if(!RecordController::validProjFormRecord($pid, $fid, $rid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);
        $record = RecordController::getRecord($rid);
        $owner = User::where('id', '=', $record->owner)->first();

        return view('records.show', compact('record', 'form', 'pid', 'owner'));
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($pid, $fid, $rid)
	{
        if(!\Auth::user()->isOwner(RecordController::getRecord($rid)) && !RecordController::checkPermissions($fid, 'modify')) {
            return redirect('projects/'.$pid.'/forms/'.$fid);
        }

        if(!RecordController::validProjFormRecord($pid, $fid, $rid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);
        $record = RecordController::getRecord($rid);

        return view('records.edit', compact('record', 'form'));
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($pid, $fid, $rid, Request $request)
	{
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        foreach($request->all() as $key => $value){
            if(!is_numeric($key)){
                continue;
            }
            $message = FieldValidation::validateField($key, $value, $request);
            if($message != ''){
                flash()->error($message);

                return redirect()->back()->withInput();
            }
        }

        $record = Record::where('rid', '=', $rid)->first();
        $record->updated_at = Carbon::now();
        $record->save();

        $revision = RevisionController::storeRevision($record->rid, 'edit');

        foreach($request->all() as $key => $value){
            if(!is_numeric($key)){
                continue;
            }
            $field = FieldController::getField($key);
            if($field->type=='Text'){
                //we need to check if the field exist first
                if(TextField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $tf = TextField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $tf->text = $value;
                    $tf->save();
                }else {
                    $tf = new TextField();
                    $tf->flid = $field->flid;
                    $tf->rid = $record->rid;
                    $tf->text = $value;
                    $tf->save();
                }
            } else if($field->type=='Rich Text'){
                //we need to check if the field exist first
                if(RichTextField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $rtf = RichTextField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $rtf->rawtext = $value;
                    $rtf->save();
                }else {
                    $rtf = new RichTextField();
                    $rtf->flid = $field->flid;
                    $rtf->rid = $record->rid;
                    $rtf->rawtext = $value;
                    $rtf->save();
                }
            } else if($field->type=='Number'){
                //we need to check if the field exist first
                if(NumberField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $nf = NumberField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $nf->number = $value;
                    $nf->save();
                }else {
                    $nf = new NumberField();
                    $nf->flid = $field->flid;
                    $nf->rid = $record->rid;
                    $nf->number = $value;
                    $nf->save();
                }
            } else if($field->type=='List'){
                //we need to check if the field exist first
                if(ListField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $lf = ListField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $lf->option = $value;
                    $lf->save();
                }else {
                    $lf = new ListField();
                    $lf->flid = $field->flid;
                    $lf->rid = $record->rid;
                    $lf->option = $value;
                    $lf->save();
                }
            } else if($field->type=='Multi-Select List'){
                //we need to check if the field exist first
                if(MultiSelectListField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $mslf = MultiSelectListField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $mslf->options = FieldController::msListArrayToString($value);
                    $mslf->save();
                }else {
                    $mslf = new MultiSelectListField();
                    $mslf->flid = $field->flid;
                    $mslf->rid = $record->rid;
                    $mslf->options = FieldController::msListArrayToString($value);
                    $mslf->save();
                }
            } else if($field->type=='Generated List'){
                //we need to check if the field exist first
                if(GeneratedListField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $glf = GeneratedListField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $glf->options = FieldController::msListArrayToString($value);
                    $glf->save();
                }else {
                    $glf = new GeneratedListField();
                    $glf->flid = $field->flid;
                    $glf->rid = $record->rid;
                    $glf->options = FieldController::msListArrayToString($value);
                    $glf->save();
                }
            } else if($field->type=='Date'){
                //we need to check if the field exist first
                if(DateField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $df = DateField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $df->circa = $request->input('circa_'.$field->flid, '');
                    $df->month = $request->input('month_'.$field->flid);
                    $df->day = $request->input('day_'.$field->flid);
                    $df->year = $request->input('year_'.$field->flid);
                    $df->era = $request->input('era_'.$field->flid, 'CE');
                    $df->save();
                }else {
                    $df = new DateField();
                    $df->flid = $field->flid;
                    $df->rid = $record->rid;
                    $df->circa = $request->input('circa_'.$field->flid, '');
                    $df->month = $request->input('month_'.$field->flid);
                    $df->day = $request->input('day_'.$field->flid);
                    $df->year = $request->input('year_'.$field->flid);
                    $df->era = $request->input('era_'.$field->flid, 'CE');
                    $df->save();
                }
            } else if($field->type=='Schedule'){
                //we need to check if the field exist first
                if(ScheduleField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $sf = ScheduleField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $sf->events = FieldController::msListArrayToString($value);
                    $sf->save();
                }else {
                    $sf = new ScheduleField();
                    $sf->flid = $field->flid;
                    $sf->rid = $record->rid;
                    $sf->events = FieldController::msListArrayToString($value);
                    $sf->save();
                }
            } else if($field->type=='Geolocator'){
                //we need to check if the field exist first
                if(GeolocatorField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $gf = GeolocatorField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                    $gf->locations = FieldController::msListArrayToString($value);
                    $gf->save();
                }else {
                    $gf = new GeolocatorField();
                    $gf->flid = $field->flid;
                    $gf->rid = $record->rid;
                    $gf->locations = FieldController::msListArrayToString($value);
                    $gf->save();
                }
            } else if($field->type=='Documents'
                    && (DocumentsField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null
                    | glob(env('BASE_PATH').'storage/app/tmpFiles/'.$value.'/*.*') != false)){
                //we need to check if the field exist first
                if(DocumentsField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $df = DocumentsField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                }else {
                    $df = new DocumentsField();
                    $df->flid = $field->flid;
                    $df->rid = $record->rid;
                    $newPath = env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid;
                    mkdir($newPath,0775,true);
                }
                //clear the old files before moving the update over
                foreach (new \DirectoryIterator(env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid) as $file) {
                    if ($file->isFile()) {
                        unlink(env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid.'/'.$file->getFilename());
                    }
                }
                //build new stuff
                $infoString = '';
                if(file_exists(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value)) {
                    $types = FieldController::getMimeTypes();
                    foreach (new \DirectoryIterator(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value) as $file) {
                        if ($file->isFile()) {
                            if(!array_key_exists($file->getExtension(),$types))
                                $type = 'application/octet-stream';
                            else
                                $type =  $types[$file->getExtension()];
                            $info = '[Name]' . $file->getFilename() . '[Name][Size]' . $file->getSize() . '[Size][Type]' . $type . '[Type]';
                            if ($infoString == '') {
                                $infoString = $info;
                            } else {
                                $infoString .= '[!]' . $info;
                            }
                            rename(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value . '/' . $file->getFilename(),
                                env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid . '/' . $file->getFilename());
                        }
                    }
                }
                $df->documents = $infoString;
                $df->save();
            } else if($field->type=='Gallery'
                    && (GalleryField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null
                    | glob(env('BASE_PATH').'storage/app/tmpFiles/'.$value.'/*.*') != false)){
                //we need to check if the field exist first
                if(GalleryField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first() != null){
                    $gf = GalleryField::where('rid', '=', $rid)->where('flid', '=', $field->flid)->first();
                }else {
                    $gf = new GalleryField();
                    $gf->flid = $field->flid;
                    $gf->rid = $record->rid;
                    $newPath = env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid;
                    mkdir($newPath,0775,true);
                    mkdir($newPath.'/thumbnail',0775,true);
                    mkdir($newPath.'/medium',0775,true);
                }
                //clear the old files before moving the update over
                foreach (new \DirectoryIterator(env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid) as $file) {
                    if ($file->isFile()) {
                        unlink(env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid.'/'.$file->getFilename());
                        unlink(env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid.'/thumbnail/'.$file->getFilename());
                        unlink(env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid.'/medium/'.$file->getFilename());
                    }
                }
                //build new stuff
                $infoString = '';
                if(file_exists(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value)) {
                    $types = FieldController::getMimeTypes();
                    foreach (new \DirectoryIterator(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value) as $file) {
                        if ($file->isFile()) {
                            if(!array_key_exists($file->getExtension(),$types))
                                $type = 'application/octet-stream';
                            else
                                $type =  $types[$file->getExtension()];
                            $info = '[Name]' . $file->getFilename() . '[Name][Size]' . $file->getSize() . '[Size][Type]' . $type . '[Type]';
                            if ($infoString == '') {
                                $infoString = $info;
                            } else {
                                $infoString .= '[!]' . $info;
                            }
                            rename(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value . '/' . $file->getFilename(),
                                env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid . '/' . $file->getFilename());
                            rename(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value . '/thumbnail/' . $file->getFilename(),
                                env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid . '/thumbnail/' . $file->getFilename());
                            rename(env('BASE_PATH') . 'storage/app/tmpFiles/' . $value . '/medium/' . $file->getFilename(),
                                env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid.'/r'.$record->rid.'/fl'.$field->flid . '/medium/' . $file->getFilename());
                        }
                    }
                }
                $gf->images = $infoString;
                $gf->save();
            }
        }

        $revision->oldData = RevisionController::buildDataArray($record);
        $revision->save();

        flash()->overlay('Your record has been successfully updated!', 'Good Job!');

        return redirect('projects/'.$pid.'/forms/'.$fid.'/records/'.$rid);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($pid, $fid, $rid)
	{
        if(!\Auth::user()->isOwner(RecordController::getRecord($rid)) && !RecordController::checkPermissions($fid, 'destroy') ) {
            return redirect('projects/'.$pid.'/forms/'.$fid);
        }

        if(!RecordController::validProjFormRecord($pid, $fid, $rid)){
            return redirect('projects/'.$pid.'forms/');
        }

        $record = RecordController::getRecord($rid);

        RevisionController::storeRevision($record->rid, 'delete');

        //if directory r[rid] exists
        //  destroy directory

        $record->delete();

        flash()->overlay('Your record has been successfully deleted!', 'Good Job!');
	}

    public function deleteAllRecords($pid, $fid)
    {
        $form = FormController::getForm($fid);
        if(!\Auth::user()->admin && \Auth::user()->isFormAdmin($form)){
            flash()->overlay('You do not have permission for that.', 'Whoops.');
        }
        else {
            $records = Record::where('fid', '=', $fid)->get();
            foreach ($records as $record) {
                RecordController::destroy($pid, $fid, $record->rid);
            }
            flash()->overlay('All records deleted.', 'Success!');
        }
    }

    public function presetRecord(Request $request)
    {
        $name = $request->name;
        $rid = $request->rid;

        if(!is_null(RecordPreset::where('rid', '=', $rid)->first()))
            flash()->overlay('Record is already a preset.');
        else {
            $record = RecordController::getRecord($rid);
            $fid = $record->fid;

            $preset = new RecordPreset();
            $preset->rid = $rid;
            $preset->fid = $fid;
            $preset->name = $name;
            $preset->save();

            flash()->overlay('Record preset saved.', 'Success!');
        }
    }

    public static function getRecord($rid)
    {
        $record = Record::where('rid', '=', $rid)->first();

        return $record;
    }

    public static function exists($rid)
    {
        return !is_null(Record::where('rid','=',$rid)->first());
    }

    public static function validProjFormRecord($pid, $fid, $rid)
    {
        $record = RecordController::getRecord($rid);
        $form = FormController::getForm($fid);
        $proj = ProjectController::getProject($pid);

        if (!FormController::validProjForm($pid, $fid))
            return false;

        if (is_null($record) || is_null($form) || is_null($proj))
            return false;
        else if ($record->fid == $form->fid)
            return true;
        else
            return false;
    }

    private function checkPermissions($fid, $permission='')
    {
        switch($permission){
            case 'ingest':
                if(!(\Auth::user()->canIngestRecords(FormController::getForm($fid))))
                {
                    flash()->overlay('You do not have permission to create records for that form.', 'Whoops.');
                    return false;
                }
                return true;
            case 'modify':
                if(!(\Auth::user()->canModifyRecords(FormController::getForm($fid))))
                {
                    flash()->overlay('You do not have permission to edit records for that form.', 'Whoops.');
                    return false;
                }
                return true;
            case 'destroy':
                if(!(\Auth::user()->canDestroyRecords(FormController::getForm($fid))))
                {
                    flash()->overlay('You do not have permission to delete records for that form.', 'Whoops.');
                    return false;
                }
                return true;
            default:
                if(!(\Auth::user()->inAFormGroup(FormController::getForm($fid))))
                {
                    flash()->overlay('You do not have permission to view records for that form.', 'Whoops.');
                    return false;
                }
                return true;
        }
    }


    /**
     *
     * Display a view for mass assigning a value to many records at once
     *
     * @param $pid
     * @param $fid
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function showMassAssignmentView($pid,$fid){


        if(!$this->checkPermissions($fid,'modify')){
            return redirect()->back();
        }

        $form = FormController::getForm($fid);
        $fields = $form->fields()->get();
        return view('records.mass-assignment',compact('form','fields','pid','fid'));
    }

    /**
     *
     * Mass assign a value to many records at once, similar to update, but loops through all of them
     *
     * @param $pid
     * @param $fid
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function massAssignRecords($pid, $fid, Request $request)
    {

        if(!$this->checkPermissions($fid,'modify')){
            return redirect()->back();
        }

        $flid = $request->input("field_selection");
        if (!is_numeric($flid)) {
            flash()->overlay("That is not a valid field");
            return redirect()->back();
        }

        if($request->has($flid)) {
            $form_field_value = $request->input($flid); //Note this only works when there is one form element being submitted, so if you have more, check Date
        }
        else{
            flash()->overlay("You didn't provide a value to assign to the records","Whoops.");
            return redirect()->back();
        }

        if ($request->has("overwrite")) {
            $overwrite = $request->input("overwrite"); //Overwrite field in all records, even if it has data
        } else {
            $overwrite = 0;
        }


        $field = Field::find($flid);
        foreach (Form::find($fid)->records()->get() as $record) {
            if ($field->type == "Text") {
                $matching_record_fields = $record->textfields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $textfield = $matching_record_fields->first();
                    if ($overwrite == true || $textfield->text == "" || is_null($textfield->text)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $textfield->text = $form_field_value;
                        $textfield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $tf = new TextField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $tf->flid = $field->flid;
                    $tf->rid = $record->rid;
                    $tf->text = $form_field_value;
                    $tf->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            } elseif ($field->type == "Rich Text") {
                $matching_record_fields = $record->richtextfields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $richtextfield = $matching_record_fields->first();
                    if ($overwrite == true || $richtextfield->rawtext == "" || is_null($richtextfield->rawtext)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $richtextfield->rawtext = $form_field_value;
                        $richtextfield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $rtf = new RichTextField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $rtf->flid = $field->flid;
                    $rtf->rid = $record->rid;
                    $rtf->rawtext = $form_field_value;
                    $rtf->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            } elseif ($field->type == "Number") {
                $matching_record_fields = $record->numberfields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $numberfield = $matching_record_fields->first();
                    if ($overwrite == true || $numberfield->number == "" || is_null($numberfield->number)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $numberfield->number = $form_field_value;
                        $numberfield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $nf = new NumberField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $nf->flid = $field->flid;
                    $nf->rid = $record->rid;
                    $nf->number = $form_field_value;
                    $nf->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            } elseif ($field->type == "List") {
                $matching_record_fields = $record->listfields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $listfield = $matching_record_fields->first();
                    if ($overwrite == true || $listfield->option == "" || is_null($listfield->option)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $listfield->option = $form_field_value;
                        $listfield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $lf = new ListField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $lf->flid = $field->flid;
                    $lf->rid = $record->rid;
                    $lf->option = $form_field_value;
                    $lf->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            } elseif ($field->type == "Multi-Select List") {
                $matching_record_fields = $record->multiselectlistfields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $multiselectlistfield = $matching_record_fields->first();
                    if ($overwrite == true || $multiselectlistfield->options == "" || is_null($multiselectlistfield->options)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $multiselectlistfield->options = implode("[!]", $form_field_value);
                        $multiselectlistfield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $mslf = new MultiSelectListField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $mslf->flid = $field->flid;
                    $mslf->rid = $record->rid;
                    $mslf->options = implode("[!]", $form_field_value);
                    $mslf->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            } elseif ($field->type == "Generated List") {
                $matching_record_fields = $record->generatedlistfields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $generatedlistfield = $matching_record_fields->first();
                    if ($overwrite == true || $generatedlistfield->options == "" || is_null($generatedlistfield->options)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $generatedlistfield->options = implode("[!]", $form_field_value);
                        $generatedlistfield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $glf = new GeneratedListField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $glf->flid = $field->flid;
                    $glf->rid = $record->rid;
                    $glf->options = implode("[!]", $form_field_value);
                    $glf->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            } elseif ($field->type == "Date") {
                $matching_record_fields = $record->datefields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $datefield = $matching_record_fields->first();
                    if ($overwrite == true || $datefield->month == "" || is_null($datefield->month)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $datefield->circa = $request->input('circa_' . $flid, '');
                        $datefield->month = $request->input('month_' . $flid);
                        $datefield->day = $request->input('day_' . $flid);
                        $datefield->year = $request->input('year_' . $flid);
                        $datefield->era = $request->input('era_' . $flid, 'CE');
                        $datefield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $df = new DateField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $df->circa = $request->input('circa_' . $flid, '');
                    $df->month = $request->input('month_' . $flid);
                    $df->day = $request->input('day_' . $flid);
                    $df->year = $request->input('year_' . $flid);
                    $df->era = $request->input('era_' . $flid, 'CE');
                    $df->rid = $record->rid;
                    $df->flid = $flid;
                    $df->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            } elseif ($field->type == "Schedule") {
                $matching_record_fields = $record->schedulefields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $schedulefield = $matching_record_fields->first();
                    if ($overwrite == true || $schedulefield->events == "" || is_null($schedulefield->events)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $schedulefield->events = implode("[!]", $form_field_value);
                        $schedulefield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $sf = new ScheduleField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $sf->flid = $field->flid;
                    $sf->rid = $record->rid;
                    $sf->events = implode("[!]", $form_field_value);
                    $sf->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            }
            elseif($field->type == "Geolocator"){
                $matching_record_fields = $record->geolocatorfields()->where("flid", '=', $flid)->get();
                $record->updated_at = Carbon::now();
                $record->save();
                if ($matching_record_fields->count() > 0) {
                    $geolocatorfield = $matching_record_fields->first();
                    if ($overwrite == true || $geolocatorfield->locations == "" || is_null($geolocatorfield->locations)) {
                        $revision = RevisionController::storeRevision($record->rid, 'edit');
                        $geolocatorfield->locations = implode("[!]", $form_field_value);
                        $geolocatorfield->save();
                        $revision->oldData = RevisionController::buildDataArray($record);
                        $revision->save();
                    } else {
                        continue;
                    }
                } else {
                    $gf = new GeolocatorField();
                    $revision = RevisionController::storeRevision($record->rid, 'edit');
                    $gf->flid = $field->flid;
                    $gf->rid = $record->rid;
                    $gf->locations = implode("[!]", $form_field_value);
                    $gf->save();
                    $revision->oldData = RevisionController::buildDataArray($record);
                    $revision->save();
                }
            }
        }

        flash()->overlay("The records were updated","Good Job!");
        return redirect()->action('RecordController@index',compact('pid','fid'));
    }
}
