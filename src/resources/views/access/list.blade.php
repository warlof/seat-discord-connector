@extends('web::layouts.grids.3-9')

@section('title', trans('web::seat.access'))
@section('page_header', trans('web::seat.access'))

@section('left')

    @include('discord-connector::access.includes.mapping-creation')
    
@stop

@section('right')

    @include('discord-connector::access.includes.mapping-table')

@stop

@push('javascript')
    <script type="application/javascript">
        function getCorporationTitle() {
            $('#discord-title-id').empty();

            $.ajax('{{ route('discord-connector.json.titles') }}', {
                data: {
                    corporation_id: $('#discord-corporation-id').val()
                },
                dataType: 'json',
                method: 'GET',
                success: function(data){
                    for (var i = 0; i < data.length; i++) {
                        $('#discord-title-id').append($('<option></option>').attr('value', data[i].title_id).text(data[i].name));
                    }
                }
            });
        }

        $('#discord-type').change(function(){
            $.each(['discord-group-id', 'discord-role-id', 'discord-corporation-id', 'discord-title-id', 'discord-alliance-id'], function(key, value){
                if (value === ('discord-' + $('#discord-type').val() + '-id')) {
                    $(('#' + value)).prop('disabled', false);
                } else {
                    $(('#' + value)).prop('disabled', true);
                }
            });

            if ($('#discord-type').val() === 'title') {
                $('#discord-corporation-id, #discord-title-id').prop('disabled', false);
            }
        }).select2();

        $('#discord-corporation-id').change(function(){
            getCorporationTitle();
        });

        $('#discord-group-id, #discord-role-id, #discord-corporation-id, #discord-title-id, #discord-alliance-id, #discord-discord-role-id').select2();

        $('#discord-tabs').find('a').click(function(e){
            e.preventDefault();
            $(this).tab('show');
        });

        $(document).ready(function(){
            getCorporationTitle();
        });
    </script>
@endpush