        <div class="topbar">
            <div class="container">
                <div class="row">

                    <div class="col-md-12 col-sm-12 center-xs text-right">
                        <div class="social-topbar">
                            @php $style = 'style="visibility: hidden;"' @endphp
                            <ul class="language-container list-inline" {!! isset($hide_lang_switcher) && $hide_lang_switcher ? $style : '' !!}> <!-- social-small -->
                                @foreach ($lang_with_flags as $lang)
                                    @if (!in_array($lang->id, [2, 5]))
                                        <li class="text-center">
                                            <a href="{{ url("lang/{$lang->id}") }}">
                                                <small>
                                                {{ $lang->language }}
                                                <img src="{{ url($lang->flag_path) }}" width="16" height="16" alt="{{ $lang->language }} Flag" />
                                                </small>
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>

                        </div>
                    </div><!-- end col -->
                </div>
            </div>
        </div>
        <!-- end topbar -->
