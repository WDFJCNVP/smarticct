<x-public.layout>
	<x-public.main-container class='mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8'>
		<div class="w-full max-w-5xl mx-auto ">

			<!-- Header -->
			<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

				<h1 class="text-2xl font-bold text-[#181E74]">Queue</h1>

				<div class="flex-1 sm:max-w-xs mx-auto sm:mx-0">
					<input
						type="text"
						placeholder="Search Routes"
						class="w-full rounded-md border border-[#181E74] px-4 py-2 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-[#181E74]"
					/>
				</div>

				<div class="relative shrink-0">
					<select
						class="appearance-none rounded-lg border border-gray-200 bg-white px-4 pr-10 py-2.5 text-sm font-medium text-gray-700 shadow-sm focus:outline-none cursor-pointer"
					>
						<option>All Vehicles</option>
						<option>Jeep</option>
						<option>Bus</option>
						<option>Multi-cab</option>
						<option>Van</option>
					</select>

					<!-- Chevron -->
					<svg
						class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 size-4 text-gray-400 transition-transform duration-200"
						fill="none"
						stroke="currentColor"
						stroke-width="2"
						viewBox="0 0 24 24"
					>
						<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
					</svg>
				</div>

			</div>

			<div id="queue-list-container">
				@include('partials.queue-list', ['group_vehicles' => $group_vehicles])
			</div>

		</div>
	</x-public.main-container>

	<script>
		let countdownIntervals = [];

		const formatRemaining = (remainingMs) => {
			const minutes = Math.floor(remainingMs / 60000).toString().padStart(2, '0');
			const seconds = Math.floor((remainingMs % 60000) / 1000).toString().padStart(2, '0');
			return `${minutes}:${seconds} minutes left`;
		};

		const clearCountdowns = () => {
			countdownIntervals.forEach((intervalId) => clearInterval(intervalId));
			countdownIntervals = [];
		};

		const initCountdowns = () => {
			clearCountdowns();

			document.querySelectorAll('.countdown').forEach((element) => {
				const rawDepartsAtTs = element.dataset.departsAtTs;

				if (!rawDepartsAtTs) {
					element.textContent = '--:--';
					return;
				}

				const endTime = Number(rawDepartsAtTs) * 1000;

				if (Number.isNaN(endTime)) {
					element.textContent = '--:--';
					return;
				}

				const updateCountdown = () => {
					const remaining = endTime - Date.now();

					if (remaining <= 0) {
						element.textContent = 'Departing';
						return;
					}

					element.textContent = formatRemaining(remaining);
				};

				updateCountdown();
				const intervalId = setInterval(updateCountdown, 1000);
				countdownIntervals.push(intervalId);
			});
		};

		const refreshQueueList = async () => {
			try {
				const response = await fetch('{{ route('queue.partial') }}', {
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					return;
				}

				const html = await response.text();
				const container = document.getElementById('queue-list-container');

				if (!container) {
					return;
				}

				container.innerHTML = html;
				initCountdowns();
			} catch (error) {
				// Keep silent and try again on next poll cycle.
			}
		};

		initCountdowns();
		setInterval(refreshQueueList, 5000);
	</script>

</x-public.layout>