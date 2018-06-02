@extends('web::layouts.grids.4-4-4')

@section('title', trans('web::seat.configuration'))
@section('page_header', trans('web::seat.configuration'))

@section('left')
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Configuration</h3>
        </div>
        <div class="panel-body">
            <form role="form" action="{{ route('discord-connector.oauth.configuration.post') }}" method="post" class="form-horizontal">
                {{ csrf_field() }}

                <div class="box-body">

                    <legend>Discord API</legend>

                    @if (! is_null(setting('warlof.discord-connector.credentials.client_id', true)))
                    <p class="callout callout-warning text-justify">It appears you already have a Discord API access setup.
                        In order to prevent any mistakes, <code>Client ID</code> and <code>Client Secret</code> fields have been disabled.
                        Please use the rubber in order to enable modifications.</p>
                    @endif

                    <div class="form-group">
                        <label for="discord-configuration-client" class="col-md-4">Discord Client ID</label>
                        <div class="col-md-7">
                            <div class="input-group input-group-sm">
                                @if (setting('warlof.discord-connector.credentials.client_id', true) == null)
                                <input type="text" class="form-control" id="discord-configuration-client"
                                       name="discord-configuration-client" />
                                @else
                                <input type="text" class="form-control " id="discord-configuration-client"
                                       name="discord-configuration-client" value="{{ setting('warlof.discord-connector.credentials.client_id', true) }}" readonly />
                                @endif
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-danger btn-flat" id="client-eraser">
                                        <i class="fa fa-eraser"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="discord-configuration-secret" class="col-md-4">Discord Client Secret</label>
                        <div class="col-md-7">
                            <div class="input-group input-group-sm">
                                @if (setting('warlof.discord-connector.credentials.client_secret', true) == null)
                                <input type="text" class="form-control" id="discord-configuration-secret"
                                       name="discord-configuration-secret" />
                                @else
                                <input type="text" class="form-control" id="discord-configuration-secret"
                                       name="discord-configuration-secret" value="{{ setting('warlof.discord-connector.credentials.client_secret', true) }}" readonly />
                                @endif
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-danger btn-flat" id="secret-eraser">
                                        <i class="fa fa-eraser"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="discord-configuration-bot" class="col-md-4">Discord Bot Token</label>
                        <div class="col-md-7">
                            <div class="input-group input-group-sm">
                                @if (setting('warlof.discord-connector.credentials.bot_token', true) == null)
                                    <input type="text" class="form-control" id="discord-configuration-bot"
                                           name="discord-configuration-bot" />
                                @else
                                    <input type="text" class="form-control" id="discord-configuration-bot"
                                           name="discord-configuration-bot" value="{{ setting('warlof.discord-connector.credentials.bot_token', true) }}" readonly />
                                @endif
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-danger btn-flat" id="bot-eraser">
                                        <i class="fa fa-eraser"></i>
                                    </button>
                                </span>
                            </div>
                            <span class="help-block text-justify">
                                In order to generate credentials, please go on <a href="https://discordapp.com/developers/applications/me" target="_blank">your Discord apps</a> and create a new app.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right">Update</button>
                </div>

            </form>
        </div>
    </div>
@stop

@section('center')
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Commands</h3>
        </div>
        <div class="panel-body">
            <div class="form-group">
                <div class="col-md-12">
                    @if(setting('warlof.discord-connector.credentials.token', true) == '')
                        <a href="#" type="button" class="btn btn-success btn-md col-md-12 disabled" role="button">Update Discord roles</a>
                    @else
                        <a href="{{ route('discord-connector.command.run', ['commandName' => 'discord:role:sync']) }}" type="button" class="btn btn-success btn-md col-md-12" role="button">Update Discord roles</a>
                    @endif
                    <span class="help-block">
                        This will update known roles from Discord.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <div class="col-md-12">
                    @if(setting('warlof.discord-connector.credentials.token', true) == '')
                        <a href="#" type="button" class="btn btn-danger btn-md col-md-12 disabled" role="button">Kick everybody</a>
                    @else
                        <a href="{{ route('discord-connector.command.run', ['commandName' => 'discord:user:terminator']) }}" type="button" class="btn btn-danger btn-md col-md-12" role="button">Kick everybody</a>
                    @endif
                    <span class="help-block">
                        This will kick every user from every conversations into the connected Discord Team. Please proceed carefully.
                    </span>
                </div>
            </div>
        </div>
    </div>
@stop

@section('right')
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-rss"></i> Update feed</h3>
        </div>
        <div class="panel-body" style="height: 500px; overflow-y: scroll">
            {!! $changelog !!}
        </div>
        <div class="panel-footer">
            <div class="row">
                <div class="col-md-6">
                    Installed version: <b>{{ config('discord-connector.config.version') }}</b>
                </div>
                <div class="col-md-6">
                    Latest version:
                    <a href="https://packagist.org/packages/warlof/seat-discord-connector">
                        <img src="https://poser.pugx.org/warlof/seat-discord-connector/v/stable" alt="Discord Connector Version" />
                    </a>
                </div>
            </div>
        </div>
    </div>
@stop

@push('javascript')
    <script type="application/javascript">
        $('#client-eraser').on('click', function(){
            var discord_client = $('#discord-configuration-client');
            discord_client.val('');
            discord_client.removeAttr("readonly");
        });

        $('#secret-eraser').on('click', function(){
            var discord_secret = $('#discord-configuration-secret');
            discord_secret.val('');
            discord_secret.removeAttr("readonly");
        });

        $('#bot-eraser').on('click', function(){
            var discord_secret = $('#discord-configuration-bot');
            discord_secret.val('');
            discord_secret.removeAttr("readonly");
        });

        $('[data-toggle="tooltip"]').tooltip();
    </script>
@endpush