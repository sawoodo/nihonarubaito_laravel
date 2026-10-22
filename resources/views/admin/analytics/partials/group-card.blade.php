{{-- One same-final-offer group. Shared by the upload preview and the saved
     "Open items" list so the two cannot drift apart.
     Expects: $g (group array), $showDone (bool) --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="panel {{ $g['verdict'] === 'same' ? 'panel-warning' : 'panel-danger' }}">
                    <div class="panel-heading">
                        <strong>{{ $g['verdict_text'] }}</strong>
                        <span class="text-muted" style="margin-left:8px">{{ $g['evidence'] }}</span>
                        <a href="{{ $g['final'] }}" target="_blank" rel="noopener nofollow" class="pull-right">
                            {{ $g['final'] }} <i class="fa fa-external-link"></i>
                        </a>
                    </div>
                    <div class="panel-body" style="padding-bottom:6px">
                        <p style="margin-bottom:8px">
                            <span class="text-muted">shigotoin page:</span> {{ $g['page_title'] ?: '(title not captured)' }}
                        </p>
                        @if ($g['verdict'] !== 'same')
                            <div class="alert alert-danger" style="padding:8px 12px; margin-bottom:10px">
                                These are <strong>not duplicates</strong> — do not trash either.
                                @if ($g['owner'])
                                    The page belongs to <strong>job {{ $g['owner'] }}</strong>;
                                    the other job(s) have the wrong link stored and need fixing by hand via <em>Open job</em>.
                                @else
                                    None of our jobs matches the destination page, so every link here is suspect.
                                @endif
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-condensed" style="margin-bottom:0">
                                <thead><tr>
                                    <th>Job</th><th>Age</th><th>Company</th><th>Title</th>
                                    <th>Address</th><th>Created</th><th>delete_at</th><th>Owns page?</th><th></th>
                                </tr></thead>
                                <tbody>
                                @foreach ($g['jobs'] as $j)
                                    <tr @if ($j['matches_page']) style="background:#f6fff6" @endif>
                                        <td>{{ $j['job_no'] }}</td>
                                        <td>
                                            @if (!empty($j['is_oldest']))
                                                <span class="label label-primary">OLDER</span>
                                            @else
                                                <span class="label label-default">newer</span>
                                            @endif
                                        </td>
                                        <td>{{ Str::limit($j['company'], 26) }}</td>
                                        <td>{{ Str::limit($j['title'], 46) }}</td>
                                        <td style="font-size:11px">{{ Str::limit($j['address'], 32) }}</td>
                                        <td style="white-space:nowrap">{{ $j['created'] ? date('d M Y', strtotime($j['created'])) : '—' }}</td>
                                        <td style="white-space:nowrap">{{ $j['delete_at'] ? date('d M Y', strtotime($j['delete_at'])) : '—' }}</td>
                                        <td>
                                            @if ($j['matches_page'])
                                                <span class="label label-success">YES</span>
                                            @else
                                                <span class="text-muted">no</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ url('admin/jobs/'.$j['job_no'].'/edit') }}" target="_blank"
                                               class="btn btn-xs btn-default">Open job</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="panel-footer">
                        @if ($g['verdict'] === 'same')
                            @php $n = count($g['jobs']); @endphp
                            <form method="POST" action="{{ route('admin.analytics.link-check.group-action') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="final_url" value="{{ $g['final'] }}">
                                <input type="hidden" name="job_nos" value="{{ $g['job_nos'] }}">
                                <button type="submit" name="action" value="keep_older" class="btn btn-sm btn-primary"
                                        onclick="return confirm('Keep the OLDEST job and trash the other {{ $n - 1 }}?');">
                                    Keep older (trash {{ $n - 1 }})
                                </button>
                                <button type="submit" name="action" value="keep_newer" class="btn btn-sm btn-default"
                                        onclick="return confirm('Keep the NEWEST job and trash the other {{ $n - 1 }}?');">
                                    Keep newer (trash {{ $n - 1 }})
                                </button>
                                <button type="submit" name="action" value="not_duplicate" class="btn btn-sm btn-link"
                                        onclick="return confirm('Mark as NOT a duplicate? This group will not be shown again.');">
                                    Not a duplicate
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.analytics.link-check.group-action') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="final_url" value="{{ $g['final'] }}">
                                <input type="hidden" name="job_nos" value="{{ $g['job_nos'] }}">
                                <button type="submit" name="action" value="not_duplicate" class="btn btn-sm btn-default"
                                        onclick="return confirm('Mark as NOT a duplicate? This group will not be shown again.');">
                                    Not a duplicate — stop showing this
                                </button>
                            </form>
                            <span class="text-muted" style="margin-left:10px">
                                Keep/trash is deliberately unavailable: these are different jobs.
                            </span>
                        @endif
                        @if (!empty($showDone))
                            <form method="POST" action="{{ route('admin.analytics.link-check.done') }}" style="display:inline; margin-left:8px">
                                @csrf
                                <input type="hidden" name="group_hash" value="{{ $g['hash'] }}">
                                <button type="submit" class="btn btn-sm btn-default"
                                        onclick="return confirm('Mark this group done? It will stop appearing, and no job is changed.');">
                                    <i class="fa fa-check"></i> Done
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
