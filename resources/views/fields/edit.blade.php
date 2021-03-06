@extends('app')

@section('leftNavLinks')
    @include('partials.menu.project', ['pid' => $field->pid])
    @include('partials.menu.form', ['pid' => $field->pid, 'fid' => $field->fid])
@stop

@section('content')
    <h1>Edit Field</h1>

    <hr/>

    {!! Form::model($field,  ['method' => 'PATCH', 'action' => ['FieldController@update', $field->pid, $field->fid, $field->flid]]) !!}
    @include('fields.form-edit',['submitButtonText' => 'Update Field', 'pid' => $field->pid, 'fid' => $field->fid, 'type' => $field->type, 'required' => $field->required])
    {!! Form::close() !!}

    @include('errors.list')
@stop