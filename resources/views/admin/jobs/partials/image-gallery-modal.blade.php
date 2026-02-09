<div class="modal fade" id="image-gallery" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" id="btn-refresh-images" class="btn btn-sm btn-info pull-left tip" title="Refresh Images">
                    <i class="fa fa-refresh"></i>
                </button>
                <a href="{{ url('admin/images') }}" class="btn btn-sm btn-success pull-left tw-ml-2 tip" title="Upload Image" target="_blank">
                    <i class="fa fa-upload"></i>
                </a>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Image Gallery</h4>
            </div>
            <div class="modal-header">
                <input type="text" id="txt-search-images" class="form-control input-sm" placeholder="Search images ...">
            </div>
            <div class="modal-body">
                <div id="image-gallery-error" style="display: none;"></div>
                <div class="alert alert-warning text-center" id="no-images-found" style="display: none;">
                    No images found. <a href="{{ url('admin/images') }}" target="_blank">Click here to upload images.</a>
                </div>
                <div class="row" id="image-gallery-container"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" id="btn-attach-image" class="btn btn-primary">Attach</button>
            </div>
        </div>
    </div>
</div>
