<form action="{{ url('jobs/search') }}" method="GET" class="submit-form customform">
    <div class="row">

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                <input type="text" id="query" name="query" class="form-control" value="{{ $query }}" placeholder="{{ $content->search_keywords_label }}" tabindex="1">
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-map-o"></i></span>
                <select id="prefecture_id" name="prefecture_id" class="form-control" tabindex="2">
                    @foreach ($prefectures as $id => $name)
                        <option value="{{ $id }}" {{ (string) $id === (string) $prefecture_id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-map-marker fa-spin wobble-fix"></i></span>
                <select id="area_id" name="area_id" class="form-control" tabindex="3">
                    <option value="0">Select prefecture first</option>
                </select>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <button class="btn btn-primary btn-block">{{ $content->btn_search_label }}</button>
        </div>

    </div>

    <div class="row listcheckbox">
        <div class="col-md-9">
            @if (isset($job_categories) && count($job_categories) > 0)
                <ul class="list-inline">
                    @foreach ($job_categories as $category)
                        <li class="checkbox checkbox-primary">
                            <input type="checkbox" id="category{{ $category->id }}" name="categories[]" class="styled" value="{{ $category->id }}" {{ in_array($category->id, $selected_cats) ? 'checked' : '' }}>
                            <label for="category{{ $category->id }}">
                                <small>{{ $category->$user_lang_name }}</small>
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="col-md-3 text-right">
            <a href="{{ url('/') }}" class="readmore">{{ $content->link_view_all_label }}</a>
        </div>
    </div>
</form>
