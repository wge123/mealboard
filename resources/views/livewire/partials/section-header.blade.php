{{--
    Heading of a settings section: the title and one line of help, with the
    Expects: $title, $help.
--}}
<header class="mb-5 flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
    <div class="min-w-0 flex-1 basis-72">
        <h2 class="font-display text-2xl font-semibold tracking-tight">{{ $title }}</h2>
        <p class="mt-1 max-w-prose text-sm text-base-content/70">{{ $help }}</p>
    </div>
</header>
