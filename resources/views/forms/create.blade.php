@extends('app')

@section('leftNavLinks')
    @include('partials.menu.project', ['pid' => $project->pid])
@stop

@section('content')
    <h1>Create a New Form for {{ $project->name }}</h1>

    <hr/>

    {!! Form::model($form = new \App\Form, ['url' => 'projects/'.$project->pid]) !!}
        @include('forms.form',['submitButtonText' => 'Create Form', 'pid' => $project->pid])
    {!! Form::close() !!}

    @include('errors.list')
@stop

@section('footer')
    <script>
        $('#admins').select2();
        $('#presets').select2({
            placeholder: 'Select a Preset',
            allowClear: true
        });
    </script>
@stop