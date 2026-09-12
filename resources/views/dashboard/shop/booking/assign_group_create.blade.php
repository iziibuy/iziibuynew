<x-dashboard.shop>
    
    <h3><span class="text-primary opacity-25"><i class="fas fa-list" aria-hidden="true"></i></span> {{__('words.assign_group_sec_title')}}
    </h3>
    <div class="row">
        <div class="col-lg-12">
            <div class="card">

                <div class="card-body shadow-lg">
                    <form action="{{ route('booking.manager.updatestoreprice', $user) }}" method="POST">
                        @csrf
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('words.service') }}</th>
                                    <th>{{ __('words.assign_group') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($user->services as $service)
                                    <input type="hidden" name="groups[{{ $loop->index }}][service_id]" value="{{ $service->id }}">
                                    <input type="hidden" name="groups[{{ $loop->index }}][manager_id]" value="{{ $user->id }}">
                                    <tr>
                                        <td>{{ $service->name }}</td>
                                        <td>
                                            <select name="groups[{{ $loop->index }}][price_group_id]" id="groups" class="form-control">
                                                <option value="">Choose group...</option>
                                                @foreach ($groups as $group)
                                                    @php
                                                        $userPriceGroup = $user->getPriceGroupFor($service->id, $group->id);
                                                    @endphp
                                                    <option value="{{ $group->id }}" {{ $userPriceGroup->price_group_id === $group->id ? 'selected' : null}}>{{ $group->name }}</option>
                                                @endforeach

                                            </select>
                                        </td>
                                    </tr>
                                @endforeach


                            </tbody>
                        </table>
                        <button class="btn btn-primary"> <i class="fa fa-plus-square" aria-hidden="true"></i>
                            Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-5">
        <div class="col-lg-12">
            <div class="card">

                <div class="card-body shadow-lg">
                    <form action="{{ route('booking.manager.updateManagerSchedule', $user) }}" method="POST">
                        @csrf
                        <div class="col-12 pt-4">
                            <label for="">{{ __('words.resource_time_label') }}</label>

                        </div>
                        <div class="row" id="weeks">
                            @foreach (config('app.days') as $key => $day)
                                @php
                                    $schedule = $user->getScheduleFor($day);
                                @endphp

                                <div class="col-4">
                                    <div class="form-check">
                                        <input class="form-check-input ml-1" name="days[{{ $key }}][is_open]" type="checkbox" value="1" id="date-{{ $day }}" {{ $schedule->is_open ? 'checked' : '' }}>
                                        <input name="days[{{ $key }}][day]" type="hidden" value="{{ $day }}">
                                        <label class="form-check-label ml-5" for="date-{{ $day }}">{{ ucfirst($day) }}</label>
                                    </div>
                                    <div class="row mt-2 mt-2 ml-2">
                                        <div class="">
                                            <input type="time" name="days[{{ $key }}][from_time]" value="{{ $schedule->formatTime($schedule->from_time) }}" class="form-control">
                                        </div>
                                        <div class="m-2 check">
                                            <span>To</span>
                                        </div>

                                        <div>
                                            <input type="time" name="days[{{ $key }}][to_time]" value="{{ $schedule->formatTime($schedule->to_time) }}" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button class="btn btn-primary mt-3 ml-2"> <i class="fa fa-plus-square" aria-hidden="true"></i>
                            {{ __('words.save_btn') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function availableDays() {
                const x = document.getElementsByClassName('check');
                const mycheck = document.getElementById("mycheck");
                let i;
                if (mycheck.checked == true) {

                    for (i = 0; i < x.length; i++) {
                        x[i].style.display = 'none';
                    }
                } else {
                    for (i = 0; i < x.length; i++) {
                        x[i].style.display = 'inline';
                    }


                }
            }
        </script>
    @endpush
</x-dashboard.shop>
