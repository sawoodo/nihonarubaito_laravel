@if (session('success'))
    <div class="alert tw-inline-block tw-bg-lime-500 tw-text-white tw-rounded-full tw-shadow-lg tw-shadow-gray-400 fade in">
        <button type="button" class="tw-ml-5 close" data-dismiss="alert">&times;</button>
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="alert tw-inline-block tw-bg-red-500 tw-text-white tw-rounded-full tw-shadow-lg tw-shadow-gray-400 fade in">
        <button type="button" class="tw-ml-5 close" data-dismiss="alert">&times;</button>
        {{ session('error') }}
    </div>
@endif
