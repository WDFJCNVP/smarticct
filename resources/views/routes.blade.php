<x-public.layout>
    <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
            x-data="{
                activeTab: '<%= data.activeTab %>',
                dropdownOpen: <%= data.dropdownOpen %>,
                options: <%= JSON.stringify(data.options) %>,
                select(tab){
                    this.activeTab = tab
                    this.dropdownOpen = false
                }
            }">


        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

            <!-- Text Header -->
          <h1 class="text-2xl font-bold text-[#181E74]">
            Routes
          </h1>

            <!-- Search -->
          <div class="flex-1 sm:max-w-xs mx-auto sm:mx-0">
            <div class="relative">
              <input
                  type="text"
                  placeholder="Search Routes"
                  class="w-full rounded-md border border-[#181E74] px-4 py-2 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-[#181E74]"
              />
            </div>
          </div>

            <!-- Dropdown -->
          <div class="relative">
            <button
                  @click="dropdownOpen = !dropdownOpen"
                  class="flex items-center justify-between gap-2 rounded-md px-4 py-1.5 text-sm font-medium
                          bg-[#181E74] text-white transition-colors">

                  <span x-text="activeTab === 'local' ? 'Local Trips' : 'Provincial Trips'"></span>

                  <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
            </button>

                <!-- Dropdown menu -->
            <div
                    x-show="dropdownOpen"
                    @click.outside="dropdownOpen = false"
                    x-transition
                    class="absolute right-0 z-50 mt-2 w-44 origin-top-right rounded-lg border border-gray-100 bg-white py-1 shadow-lg"
                    x-cloak
                >
                    <template x-for="option in options" :key="option.value">
                        <button
                            @click="select(option.value)"
                            class="flex w-full items-center justify-between px-4 py-2.5 text-sm hover:bg-[#181E74]/10"
                            :class="activeTab === option.value ? 'text-[#181E74] font-semibold bg-[#181E74]/5' : 'text-gray-700'"
                        >
                            <span x-text="option.label"></span>

                            <svg
                                x-show="activeTab === option.value"
                                class="size-4 text-[#181E74]"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </template>
                </div>

            </div>
          </div>

        <!-- LOCAL TRIPS SECTION -->

        <div x-show="activeTab === 'local'" id="local-trips">
          <h2 class="text-lg font-semibold text-[#181E74] mb-4">Local Trips</h2>

          <div class="rounded-xl border border-gray-100 shadow-sm bg-white overflow-hidden">
              <div class="overflow-x-auto">
                  <table class="w-full text-sm">
                      <thead>
                          <tr class="border-b border-gray-100">
                              <th class="px-6 py-4 text-left font-medium text-gray-500 w-12">#</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">City/Municipality</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">Vehicle</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">First Trip</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">Last trip</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">Fare</th>
                          </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">

                          <!-- Row 1: Naga - Ordinary Bus -->
                          <tr class="hover:bg-gray-50/50">
                              <td class="px-6 py-3 text-gray-700 align-middle" rowspan="3">1</td>
                              <td class="px-6 py-3 text-gray-700 align-middle" rowspan="3">Naga</td>
                              <td class="px-6 py-3 text-gray-700">Ordinary Bus</td>
                              <td class="px-6 py-3 text-gray-600">5:00 am</td>
                              <td class="px-6 py-3 text-gray-600">7:00 pm</td>
                              <td class="px-6 py-3 text-gray-700">P 20.00</td>
                          </tr>
                          <!-- Row 1: Naga - UV Express (highlighted) -->
                          <tr class="bg-[#181E74]/10">
                              <td class="px-6 py-3 text-[#181E74] font-medium">UV Express</td>
                              <td class="px-6 py-3 text-[#181E74] font-medium">8:00 am</td>
                              <td class="px-6 py-3 text-[#181E74] font-medium">7:00 pm</td>
                              <td class="px-6 py-3 text-[#181E74] font-medium">P 20.00</td>
                          </tr>
                          <!-- Row 1: Naga - Jeep -->
                          <tr class="hover:bg-gray-50/50">
                              <td class="px-6 py-3 text-gray-700">Jeep</td>
                              <td class="px-6 py-3 text-gray-600">6:00 am</td>
                              <td class="px-6 py-3 text-gray-600">7:00 pm</td>
                              <td class="px-6 py-3 text-gray-700">P 20.00</td>
                          </tr>

                          <!-- Row 2: Nabua - UV Express -->
                          <tr class="border-t border-gray-200 hover:bg-gray-50/50">
                              <td class="px-6 py-3 text-gray-700 align-middle" rowspan="2">2</td>
                              <td class="px-6 py-3 text-gray-700 align-middle" rowspan="2">Nabua</td>
                              <td class="px-6 py-3 text-gray-700">UV Express</td>
                              <td class="px-6 py-3 text-gray-600">5:00 am</td>
                              <td class="px-6 py-3 text-gray-600">7:00 pm</td>
                              <td class="px-6 py-3 text-gray-700">P 20.00</td>
                          </tr>
                          <!-- Row 2: Nabua - Jeep (highlighted) -->
                          <tr class="bg-[#181E74]/10">
                              <td class="px-6 py-3 text-[#181E74] font-medium">Jeep</td>
                              <td class="px-6 py-3 text-[#181E74] font-medium">5:00 am</td>
                              <td class="px-6 py-3 text-[#181E74] font-medium">7:00 pm</td>
                              <td class="px-6 py-3 text-[#181E74] font-medium">P 20.00</td>
                          </tr>

                          <!-- Row 3: Pili -->
                          <tr class="border-t border-gray-200 hover:bg-gray-50/50">
                              <td class="px-6 py-3 text-gray-700">3</td>
                              <td class="px-6 py-3 text-gray-700">Pili</td>
                              <td class="px-6 py-3 text-gray-700">Jeep</td>
                              <td class="px-6 py-3 text-gray-600">6:00 am</td>
                              <td class="px-6 py-3 text-gray-600">7:00 pm</td>
                              <td class="px-6 py-3 text-gray-700">P 20.00</td>
                          </tr>

                      </tbody>
                  </table>
              </div>
          </div>
        </div>

        <!-- PROVINCIAL TRIPS SECTION -->

        <div x-show="activeTab === 'provincial'" id="provincial-trips" class="mt-10">
          <h2 class="text-lg font-semibold text-[#181E74] mb-4">Provincial Trips</h2>

          <div class="rounded-xl border border-gray-100 shadow-sm bg-white overflow-hidden">
              <div class="overflow-x-auto">
                  <table class="w-full text-sm">
                      <thead>
                          <tr class="border-b border-gray-100">
                              <th class="px-6 py-4 text-left font-medium text-gray-500 w-12">#</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">City/Municipality</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">Vehicle</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">Day Trip</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">Night trip</th>
                              <th class="px-6 py-4 text-left font-medium text-gray-500">Action</th>
                          </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">

                          <!-- Row 1: PITX -->
                          <tr class="hover:bg-gray-50/50">
                              <td class="px-6 py-4 text-gray-700">1</td>
                              <td class="px-6 py-4 text-gray-700">PITX</td>
                              <td class="px-6 py-4 text-gray-700">DLTBco</td>
                              <td class="px-6 py-4 text-gray-600">5:00 am</td>
                              <td class="px-6 py-4 text-gray-600">7:00 pm</td>
                              <td class="px-6 py-4">
                                  <button class="rounded-md bg-[#181E74] px-4 py-1.5 text-sm font-medium text-white hover:bg-[#232ca6] transition-colors">
                                      Book now
                                  </button>
                              </td>
                          </tr>

                          <!-- Row 2: Cubao -->
                          <tr class="hover:bg-gray-50/50">
                              <td class="px-6 py-4 text-gray-700">2</td>
                              <td class="px-6 py-4 text-gray-700">Cubao</td>
                              <td class="px-6 py-4 text-gray-700">DLTBco</td>
                              <td class="px-6 py-4 text-gray-600">5:00 am</td>
                              <td class="px-6 py-4 text-gray-600">7:00 pm</td>
                              <td class="px-6 py-4">
                                  <button class="rounded-md bg-[#181E74] px-4 py-1.5 text-sm font-medium text-white hover:bg-[#232ca6] transition-colors">
                                      Book now
                                  </button>
                              </td>
                          </tr>

                      </tbody>
                  </table>
              </div>
          </div>
        </div>
    </div>
</x-public.layout>