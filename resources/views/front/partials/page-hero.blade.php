{{-- Bandeau de page interne : @include('front.partials.page-hero', ['eyebrow' => ..., 'heading' => ..., 'lead' => ...]) --}}
<div class="relative overflow-hidden bg-primary pt-[150px] pb-14 lg:pt-[180px] lg:pb-20">
    <div class="container relative px-4 mx-auto">
        @if (! empty($eyebrow))
            <span class="block mb-2 text-base font-semibold text-white/70">{{ $eyebrow }}</span>
        @endif

        <h1 class="max-w-3xl text-3xl font-bold leading-snug text-white sm:text-4xl lg:text-[42px] lg:leading-[1.2]">
            {{ $heading }}
        </h1>

        @if (! empty($lead))
            <p class="mt-4 max-w-2xl text-base text-white/80">{{ $lead }}</p>
        @endif
    </div>

    <span class="pointer-events-none absolute -right-16 -top-10 opacity-10">
        <svg width="340" height="340" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2.5c-3.6 4.2-6.5 7.8-6.5 11.2a6.5 6.5 0 0 0 13 0c0-3.4-2.9-7-6.5-11.2Z" />
        </svg>
    </span>
</div>
