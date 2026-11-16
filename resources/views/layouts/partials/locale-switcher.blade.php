<form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-2 text-sm">
    @csrf
    <button type="submit" name="locale" value="ar" @class([
        'min-h-11 px-2',
        'font-semibold text-brand' => app()->getLocale() === 'ar',
        'text-ink/70' => app()->getLocale() !== 'ar',
    ])>العربية</button>
    <button type="submit" name="locale" value="en" @class([
        'min-h-11 px-2',
        'font-semibold text-brand' => app()->getLocale() === 'en',
        'text-ink/70' => app()->getLocale() !== 'en',
    ])>English</button>
</form>
