<a 
  href="{{ $attributes->get('href') }}" 
  class="
    relative inline-block pb-[5px] text-sm/6 font-semibold transition-colors duration-300 group
    {{ $isActive ? 'text-[#181E74]' : 'text-gray-900 hover:text-[#181E74]' }}
  "
>
  {{ $slot }}
  
  <span 
    class="
      absolute bottom-0 left-1/2 h-[2px] -translate-x-1/2 bg-[#181E74] transition-all duration-300 ease-in-out
      {{ $isActive ? 'w-full' : 'w-0 group-hover:w-full' }}
    "
  ></span>
</a>  