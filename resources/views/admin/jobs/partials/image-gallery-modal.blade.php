<div id="image-gallery" class="modal fade" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">

                <div class="btn-toolbar pull-right" role="toolbar">
                    <button id="refresh" class="btn btn-success btn-xs tip" role="group" title="Refresh">
                        <i class="fa fa-refresh"></i>
                    </button>
                    <a href="{{ url('admin/images') }}" class="btn btn-info btn-xs tip" role="group" title="Upload" target="_blank">
                        <i class="fa fa-upload"></i>
                    </a>
                    <button class="btn btn-default btn-xs tip" data-dismiss="modal" aria-label="Close" role="group" title="Close">
                        <i class="fa fa-times"></i>
                    </button>
                </div>

                <h4 class="modal-title">
                    Select Image
                    <small class="col-md-offset-1">
                        <label for="search">Search</label>
                    </small>
                    <input type="text" id="search" class="input-sm">
                </h4>

            </div>

            <div class="modal-body">
                <div class="row" id="error-message">
                    <div class="col-md-12" id="image-not-selected-message"></div>
                </div>

                <div class="row" id="no-image-found">
                    <div class="col-md-12">
                        <div class="alert alert-danger text-center">
                            <h3>
                                <i class="fa fa-warning"></i>
                                No image found...!
                            </h3>
                        </div>
                        <h4 class="text-center">
                            <a href="{{ url('admin/images') }}" class="btn btn-primary">
                                Click here to upload images
                            </a>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="btn-attach-image" class="btn btn-primary">
                    <i class="fa fa-paperclip"></i> Attach
                </button>
            </div>
        </div>
    </div>
</div>
