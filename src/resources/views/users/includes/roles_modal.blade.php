<div class="modal fade" id="user-channels" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close pull-right" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <span id="discord_username"></span>
                    (<span id="seat_username"></span>) is member of following
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <table class="table table-condensed table-hover" id="roles">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="overlay">
                    <i class="fa fa-refresh fa-spin"></i>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-xs btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>