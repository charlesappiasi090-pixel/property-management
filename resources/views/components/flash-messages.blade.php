@php
    /*
    | Explicit list rather than session()->get('status'): the enum guarantees
    | only known keys are ever rendered, so a stray `->with('error', ...)` in a
    | controller can never inject unescaped markup into a flash slot.
    */
    $flashes = array_filter([
        'success' => session('success'),
        'status' => session('status'),
        'message' => session('message'),
        'info' => session('info'),
        'warning' => session('warning'),
        'error' => session('error'),
    ]);

    $styles = [
        'success' => ['icon' => 'check-circle', 'class' => 'ph-alert-success', 'role' => 'status'],
        'status' => ['icon' => 'check-circle', 'class' => 'ph-alert-success', 'role' => 'status'],
        'info' => ['icon' => 'information-circle', 'class' => 'ph-alert-info', 'role' => 'status'],
        'message' => ['icon' => 'information-circle', 'class' => 'ph-alert-info', 'role' => 'status'],
        'warning' => ['icon' => 'exclamation-triangle', 'class' => 'ph-alert-warning', 'role' => 'status'],
        'error' => ['icon' => 'x-circle', 'class' => 'ph-alert-danger', 'role' => 'alert'],
    ];
@endphp

@if (count($flashes) > 0)
    {{--
        `role="alert"` on the error variant makes a screen reader interrupt
        immediately; the informational variants use `role="status"` so they
        are announced politely without cutting off whatever the user is
        currently reading. Errors that result from a failed write deserve the
        interruption; a "saved" confirmation does not.
    --}}
    <div class="space-y-3">
        @foreach ($flashes as $key => $message)
            @php $style = $styles[$key]; @endphp

            <div
                class="{{ $style['class'] }}"
                role="{{ $style['role'] }}"
            >
                <x-icon :name="$style['icon']" class="size-5 shrink-0" />

                <div class="min-w-0 flex-1">
                    @if (is_array($message))
                        {{-- Supports ['title' => ..., 'body' => ...] without a second component. --}}
                        @foreach ($message as $line)
                            <p class="text-sm">{{ $line }}</p>
                        @endforeach
                    @else
                        <p class="text-sm">{{ $message }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
