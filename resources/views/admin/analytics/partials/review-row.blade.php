{{-- One staff-review row. Shared by the preview and the saved list.
     Expects: $r (review array), $showDone (bool) --}}
                                <tr @if ($r['class'] === 'WRONG_TARGET') class="danger" @endif>
                                    <td>{{ $r['job_no'] }}</td>
                                    <td>
                                        <span class="label label-{{ $r['class'] === 'WRONG_TARGET' ? 'danger' : 'default' }}">{{ $r['class'] }}</span>
                                        <br><small class="text-muted">status {{ $r['status'] }}</small>
                                    </td>
                                    <td style="max-width: 260px">
                                        {{ Str::limit($r['title'], 58) }}
                                        <br><small class="text-muted">{{ Str::limit($r['company'] ?? '', 30) }}</small>
                                    </td>
                                    <td style="font-size: 11px; word-break: break-all">
                                        <a href="{{ $r['url'] }}" target="_blank" rel="noopener nofollow">{{ $r['url'] }}</a>
                                        @if ($r['class'] === 'WRONG_TARGET' && !empty($r['final_url']))
                                            <br><span class="text-danger">redirects to {{ $r['final_url'] }}</span>
                                            @if (!empty($r['page_title']))
                                                <br><em class="text-muted">{{ Str::limit($r['page_title'], 90) }}</em>
                                            @endif
                                        @endif
                                        @if (!empty($r['note']))<br><em class="text-muted">{{ $r['note'] }}</em>@endif
                                    </td>
                                    <td style="white-space:nowrap">
                                        @if ($r['status'] === 3)
                                            <form method="POST" action="{{ route('admin.analytics.link-check.row-action') }}" style="display:inline">
                                                @csrf
                                                <input type="hidden" name="job_no" value="{{ $r['job_no'] }}">
                                                <input type="hidden" name="current_link" value="{{ $r['current_link'] }}">
                                                <button type="submit" name="action" value="expire" class="btn btn-xs btn-danger"
                                                        onclick="return confirm('Expire job {{ $r['job_no'] }}? It stops being listed.');">Expire</button>
                                                <button type="submit" name="action" value="extend" class="btn btn-xs btn-success"
                                                        onclick="return confirm('Give job {{ $r['job_no'] }} another 7 days?');">Extend 7d</button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                        <a href="{{ url('admin/jobs/'.$r['job_no'].'/edit') }}" target="_blank" class="btn btn-xs btn-default">Open job</a>
                                        @if (!empty($showDone) && !empty($r['item_key']))
                                            <form method="POST" action="{{ route('admin.analytics.link-check.done') }}" style="display:inline">
                                                @csrf
                                                <input type="hidden" name="item_key" value="{{ $r['item_key'] }}">
                                                <button type="submit" class="btn btn-xs btn-default"
                                                        onclick="return confirm('Mark job {{ $r['job_no'] }} done? No job is changed.');">Done</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
