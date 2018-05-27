@extends('web::layouts.grids.3-9')

@section('title', trans_choice('web::seat.log', 2))
@section('page_header', trans_choice('web::seat.log', 2))

@section('left')
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Information</h3>
        </div>
        <div class="panel-body">
            <p>This section display Slack related event.
                You will for example find which user and when it has been kicked or invited to a channel.</p>
            <p>It will display settings issue as well like if people didn't change their mail address.</p>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">Legend</h3>
        </div>
        <div class="box-body">
            <ul class="list-unstyled">
                <li>
                    <span class="label label-info">&nbsp;</span> Common information message</li>
                <li>
                    <span class="label label-success">&nbsp;</span> Success information message, like good news</li>
                <li>
                    <span class="label label-warning">&nbsp;</span> Important information message</li>
                <li>
                    <span class="label label-danger">&nbsp;</span> Information message related to an error</li>
            </ul>
        </div>
    </div>
@stop

@section('right')
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Last Event logs</h3>
        </div>
        <div class="panel-body">
            <table class="table table-condensed table-hover table-responsive no-margin" id="logs-table" data-page-length="25">
                <thead>
                    <tr>
                        <th>{{ trans('web::seat.date') }}</th>
                        <th>{{ trans('web::seat.category') }}</th>
                        <th>{{ trans('web::seat.message') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
        <div class="panel-footer clearfix">
            @if($logCount == 0)
                <a href="#" type="button" class="btn btn-danger btn-sm pull-right disabled" role="button">
                    Clear</a>
            @else
                <a href="{{ route('slackbot.command.run', ['commandName' => 'slack:logs:clear']) }}" type="button"
                   class="btn btn-danger btn-sm pull-right" role="button">Clear</a>
            @endif
        </div>
    </div>
@stop

@push('javascript')
<script type="text/javascript">
    $(function(){
        $('table#logs-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('discord-connector.json.logs') }}',
            columns: [
                {data: 'created_at'},
                {data: 'event', render: function(data){
                    switch (data) {
                        case 'invite':
                            return '<span class="label label-success">' + data + '</span>';
                        case 'kick':
                            return '<span class="label label-warning">' + data + '</span>';
                        case 'sync':
                            return '<span class="label label-danger">' + data + '</span>';
                        default:
                            return '<span class="label label-info">' + data + '</span>';
                    }
                }},
                {data: 'message'}
            ],
            "fnDrawCallback": function(){
                $(document).ready(function(){
                    $('img').unveil(100);
                });
            }
        });
    });
</script>
@endpush