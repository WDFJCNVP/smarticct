<div>
  <button type="submit" {{ $attributes->merge(['class'=>'flex w-full justify-center rounded-md bg-primary px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary cursor-pointer']) }}>
    {{ $slot }}
  </button>
</div>