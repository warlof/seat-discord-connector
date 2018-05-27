<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{{ trans_choice('discord-connector::seat.authorization', 2) }}</h3>
    </div>
    <div class="panel-body">

        <ul class="nav nav-pills" id="discord-tabs" role="tablist">
            <li role="presentation" class="active">
                <a href="#discord-public" role="tab" data-toggle="tab">{{ trans('discord-connector::seat.public_filter') }}</a>
            </li>
            <li role="presentation">
                <a href="#discord-username" role="tab" data-toggle="tab">{{ trans('discord-connector::seat.user_filter') }}</a>
            </li>
            <li role="presentation">
                <a href="#discord-role" role="tab" data-toggle="tab">{{ trans('discord-connector::seat.role_filter') }}</a>
            </li>
            <li role="presentation">
                <a href="#discord-corporation" role="tab" data-toggle="tab">{{ trans('discord-connector::seat.corporation_filter') }}</a>
            </li>
            <li role="presentation">
                <a href="#discord-title" role="tab" data-toggle="tab">{{ trans('discord-connector::seat.title_filter') }}</a>
            </li>
            <li role="presentation">
                <a href="#discord-alliance" role="tab" data-toggle="tab">{{ trans('discord-connector::seat.alliance_filter') }}</a>
            </li>
        </ul>

        <div class="tab-content">
            <div role="tabpanel" class="tab-pane fade in active" id="discord-public">
                @include('discord-connector::access.includes.subs.public-mapping-tab')
            </div>
            <div role="tabpanel" class="tab-pane fade" id="discord-username">
                @include('discord-connector::access.includes.subs.user-mapping-tab')
            </div>
            <div role="tabpanel" class="tab-pane fade" id="discord-role">
                @include('discord-connector::access.includes.subs.role-mapping-tab')
            </div>
            <div role="tabpanel" class="tab-pane fade" id="discord-corporation">
                @include('discord-connector::access.includes.subs.corporation-mapping-tab')
            </div>
            <div role="tabpanel" class="tab-pane fade" id="discord-title">
                @include('discord-connector::access.includes.subs.title-mapping-tab')
            </div>
            <div role="tabpanel" class="tab-pane fade" id="discord-alliance">
                @include('discord-connector::access.includes.subs.alliance-mapping-tab')
            </div>
        </div>
    </div>
</div>