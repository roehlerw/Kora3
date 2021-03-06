@extends('app')

@section('leftNavLinks')
    @include('partials.menu.project', ['pid' => $field->pid])
    @include('partials.menu.form', ['pid' => $field->pid, 'fid' => $field->fid])
@stop

@section('content')
    <span><h1>{{ $field->name }}</h1></span>
    <div><b>Internal Name:</b> {{ $field->slug }}</div>
    <div><b>Type:</b> {{ $field->type }}</div>
    <div><b>Description:</b> {{ $field->desc }}</div>
    <hr/>

    @yield('fieldOptions')
@stop