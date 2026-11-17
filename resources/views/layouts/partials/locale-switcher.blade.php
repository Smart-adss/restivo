<form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-1 rounded-md border border-line bg-surface p-1 text-sm">
    @csrf
    <button type="submit" name="locale" value="ar" @class([
        'min-h-9 rounded-sm px-2.5 transition',
        'bg-canvas font-semibold text-brand' => app()->getLocale() === 'ar',
        'text-ink/65 hover:text-ink' => app()->getLocale() !== 'ar',
    ])>العربية</button>
    <button type="submit" name="locale" value="en" @class([
        'min-h-9 rounded-sm px-2.5 transition',
        'bg-canvas font-semibold text-brand' => app()->getLocale() === 'en',
        'text-ink/65 hover:text-ink' => app()->getLocale() !== 'en',
    ])>English</button>
</form>
