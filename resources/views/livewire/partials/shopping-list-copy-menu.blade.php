{{-- Copy the list as markdown or plain text. $up opens the menu upward (phone bottom bar); $iconOnly hides the label. --}}
<div class="dropdown dropdown-end {{ ($up ?? false) ? 'dropdown-top' : '' }}" x-data="{ copied: '' }">
    <x-ui.button tabindex="0" icon="tabler-copy" aria-label="Copy list"><span class="{{ ($iconOnly ?? false) ? 'sr-only' : '' }}">Copy list</span></x-ui.button>
    <ul tabindex="0" class="dropdown-content menu z-30 my-1 w-48 rounded-box border border-base-300 bg-base-100 p-1 shadow-lg">
        <li><button type="button" class="min-h-11 sm:min-h-9" @click="navigator.clipboard.writeText(@js($markdown)); copied = 'md'; setTimeout(() => copied = '', 1500)" x-text="copied === 'md' ? 'Copied' : 'As markdown'"></button></li>
        <li><button type="button" class="min-h-11 sm:min-h-9" @click="navigator.clipboard.writeText(@js($plain)); copied = 'plain'; setTimeout(() => copied = '', 1500)" x-text="copied === 'plain' ? 'Copied' : 'As plain list'"></button></li>
    </ul>
</div>
