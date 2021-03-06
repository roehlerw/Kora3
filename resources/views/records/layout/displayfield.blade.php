<div>
    <span><b>{{ $field->name }}:</b> </span>
    <span>
        @if($field->type=='Text')
            @foreach($record->textfields as $tf)
                @if($tf->flid == $field->flid)
                    {{ $tf->text }}
                @endif
            @endforeach
        @elseif($field->type=='Rich Text')
            @foreach($record->richtextfields as $rtf)
                @if($rtf->flid == $field->flid)
                    <?php echo $rtf->rawtext ?>
                @endif
            @endforeach
        @elseif($field->type=='Number')
            @foreach($record->numberfields as $nf)
                @if($nf->flid == $field->flid)
                    <?php
                        echo $nf->number;
                        if($nf->number!='')
                            echo ' '.\App\Http\Controllers\FieldController::getFieldOption($field,'Unit');
                    ?>
                @endif
            @endforeach
        @elseif($field->type=='List')
            @foreach($record->listfields as $lf)
                @if($lf->flid == $field->flid)
                    {{ $lf->option }}
                @endif
            @endforeach
        @elseif($field->type=='Multi-Select List')
            @foreach($record->multiselectlistfields as $mslf)
                @if($mslf->flid == $field->flid)
                    @foreach(explode('[!]',$mslf->options) as $opt)
                        <div>{{ $opt }}</div>
                    @endforeach
                @endif
            @endforeach
        @elseif($field->type=='Generated List')
            @foreach($record->generatedlistfields as $glf)
                @if($glf->flid == $field->flid)
                    @foreach(explode('[!]',$glf->options) as $opt)
                        <div>{{ $opt }}</div>
                    @endforeach
                @endif
            @endforeach
        @elseif($field->type=='Date')
            @foreach($record->datefields as $df)
                @if($df->flid == $field->flid)
                    @if($df->circa==1 && \App\Http\Controllers\FieldController::getFieldOption($field,'Circa')=='Yes')
                        {{'circa '}}
                    @endif
                    @if($df->month==0 && $df->day==0)
                        {{$df->year}}
                    @elseif($df->day==0)
                        {{ $df->month.' '.$df->year }}
                    @elseif(\App\Http\Controllers\FieldController::getFieldOption($field,'Format')=='MMDDYYYY')
                        {{$df->month.'-'.$df->day.'-'.$df->year}}
                    @elseif(\App\Http\Controllers\FieldController::getFieldOption($field,'Format')=='DDMMYYYY')
                        {{$df->day.'-'.$df->month.'-'.$df->year}}
                    @elseif(\App\Http\Controllers\FieldController::getFieldOption($field,'Format')=='YYYYMMDD')
                        {{$df->year.'-'.$df->month.'-'.$df->day}}
                    @endif
                    @if(\App\Http\Controllers\FieldController::getFieldOption($field,'Era')=='Yes')
                        {{' '.$df->era}}
                    @endif
                @endif
            @endforeach
        @elseif($field->type=='Schedule')
            @if(\App\Http\Controllers\FieldController::getFieldOption($field,'Calendar')=='No')
                @foreach($record->schedulefields as $sf)
                    @if($sf->flid == $field->flid)
                        @foreach(explode('[!]',$sf->events) as $event)
                            <div>{{ $event }}</div>
                        @endforeach
                    @endif
                @endforeach
            @else
                @foreach($record->schedulefields as $sf)
                    @if($sf->flid == $field->flid)
                        <div id='calendar{{$field->flid}}'></div>
                        <script>
                            $('#calendar{{$field->flid}}').fullCalendar({
                                events: [
                                    @foreach(explode('[!]',$sf->events) as $event)
                                        {
                                        <?php
                                            $nameTime = explode(': ',$event);
                                            $times = explode(' - ',$nameTime[1]);
                                            $allDay = true;
                                            if(strpos($nameTime[1],'PM') | strpos($nameTime[1],'AM')){
                                                $allDay = false;
                                            }
                                        ?>
                                        title: '{{ $nameTime[0] }}',
                                        start: '{{ $times[0] }}',
                                        end: '{{ $times[1] }}',
                                        @if($allDay)
                                            allDay: true
                                        @else
                                            allDay: false
                                        @endif
                                    },
                                    @endforeach
                                ]
                            });
                        </script>
                    @endif
                @endforeach
            @endif
        @elseif($field->type=='Geolocator')
            @if(\App\Http\Controllers\FieldController::getFieldOption($field,'Map')=='No')
                @foreach($record->geolocatorfields as $gf)
                    @if($gf->flid == $field->flid)
                        @foreach(explode('[!]',$gf->locations) as $opt)
                            <div>{{ $opt }}</div>
                        @endforeach
                    @endif
                @endforeach
            @else
                @foreach($record->geolocatorfields as $gf)
                    @if($gf->flid == $field->flid)
                        <div id="map{{$field->flid}}" style="height:270px;"></div>
                        <?php $locs = array(); ?>
                        @foreach(explode('[!]',$gf->locations) as $location)
                            <?php
                                $loc = array();
                                $desc = explode(': ',$location)[0];
                                $x = explode(', ', explode(': ',$location)[1])[0];
                                $y = explode(', ', explode(': ',$location)[1])[1];

                                $loc['desc'] = $desc;
                                $loc['x'] = $x;
                                $loc['y'] = $y;

                                array_push($locs,$loc);
                            ?>
                        @endforeach
                    <script>
                        var map{{$field->flid}} = L.map('map{{$field->flid}}').setView([{{$locs[0]['x']}}, {{$locs[0]['y']}}], 13);
                        L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png?{foo}', {foo: 'bar'}).addTo(map{{$field->flid}});
                        @foreach($locs as $loc)
                            var marker = L.marker([{{$loc['x']}}, {{$loc['y']}}]).addTo(map{{$field->flid}});
                            marker.bindPopup("{{$loc['desc']}}");
                        @endforeach
                    </script>
                    @endif
                @endforeach
            @endif
        @elseif($field->type=='Documents')
            @foreach($record->documentsfields as $df)
                @if($df->flid == $field->flid)
                    @foreach(explode('[!]',$df->documents) as $opt)
                        @if($opt != '')
                            <?php
                            $name = explode('[Name]',$opt)[1];
                            $link = env('BASE_URL').'storage/app/files/p'.$form->pid.'/f'.$form->fid.'/r'.$record->rid.'/fl'.$field->flid.'/'.$name;
                            ?>
                            <div><a href="{{$link}}">{{$name}}</a></div>
                        @endif
                    @endforeach
                @endif
            @endforeach
        @endif
    </span>
</div>