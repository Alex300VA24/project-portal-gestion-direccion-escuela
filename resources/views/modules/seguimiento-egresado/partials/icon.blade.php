@php
  $paths = [
    'home' => 'm3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9',
    'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    'mail' => 'M4 4h16a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Zm-1 2 9 7 9-7',
    'briefcase' => 'M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M3 6h18v14H3ZM3 11a20 20 0 0 0 18 0M12 11v4',
    'building' => 'M4 21V3h12v18M16 9h4v12M2 21h20M8 7h4M8 11h4M8 15h4M9 21v-2h2v2',
    'chart' => 'M4 3v17h17M8 16v-4M13 16V8M18 16V5',
    'clipboard' => 'M9 5H5v16h14V5h-4M9 3h6v4H9ZM8 12l2 2 5-4M8 18h7',
    'arrow' => 'M5 12h14m-5-5 5 5-5 5',
    'menu' => 'M4 6h16M4 12h16M4 18h16',
    'info' => 'M12 8h.01M11 12h1v4h1M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
  ];
@endphp
<svg class="sg-svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['info'] }}" /></svg>
