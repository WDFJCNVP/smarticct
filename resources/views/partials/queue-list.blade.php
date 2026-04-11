<div class="mb-10">
    @forelse ($group_vehicles as $vehicle_type => $destinations)

        <h2 class="text-xl font-bold text-gray-800 mb-4">
            {{ $vehicle_type }}
        </h2>

        @foreach ($destinations as $destination => $queues)

            <div class="rounded-xl border border-gray-100 shadow-sm bg-white overflow-hidden mb-4">

                <!-- Destination Header -->
                <div class="px-6 pt-5 pb-3">
                    <h3 class="text-base font-semibold text-[#181E74]">
                        {{ $destination }}
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-t border-gray-200">
                                <th class="px-6 py-3 text-left text-xs text-gray-400">#</th>
                                <th class="px-6 py-3 text-left text-xs text-gray-400">Plate No.</th>
                                <th class="px-6 py-3 text-left text-xs text-gray-400">Driver Name</th>
                                <th class="px-6 py-3 text-left text-xs text-gray-400">Seat</th>
                                <th class="px-6 py-3 text-left text-xs text-gray-400">Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($queues as $index => $queue)

                                @if ($index === 0)
                                    <!-- Loading -->
                                    <tr class="bg-[#181E74]/[0.07]">
                                        <td class="px-6 py-3 font-semibold">{{ $index + 1 }}</td>
                                        <td class="px-6 py-3 font-semibold">{{ $queue->plate_number }}</td>
                                        <td class="px-6 py-3 font-semibold">{{ $queue->driver_name }}</td>
                                        <td class="px-6 py-3">{{ $queue->seat_count }} / {{ $queue->seat_capacity }}</td>
                                        <td class="px-6 py-3 text-red-500">
                                            <span class="countdown" data-departs-at-ts="{{ \Carbon\Carbon::parse($queue->departs_at)->timestamp }}"></span>
                                        </td>
                                    </tr>
                                @elseif ($index > 0 && $index < 3)  
                                    <!-- Staging -->
                                    <tr class="text-gray-400">
                                        <td class="px-6 py-3">{{ $index + 1 }}</td>
                                        <td class="px-6 py-3">{{ $queue->plate_number }}</td>
                                        <td class="px-6 py-3">{{ $queue->driver_name }}</td>
                                        <td class="px-6 py-3">{{ $queue->seat_count }} / {{ $queue->seat_capacity }}</td>
                                        <td class="px-6 py-3">--:--</td>
                                    </tr>
                                @endif

                            @endforeach
                        </tbody>

                    </table>
                </div>

            </div>

        @endforeach

    @empty
        <div class="rounded-lg border border-gray-100 bg-white p-6 text-sm text-gray-500">
            No active queue right now.
        </div>
    @endforelse
</div>
