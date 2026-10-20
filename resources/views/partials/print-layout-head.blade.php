<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@fonts
@vite(['resources/css/admin.css'])
<style>
    body { background: #f1f3f5; }
    .sheet { max-width: 900px; margin: 2rem auto; background: #fff; padding: 2.5rem; border-radius: .5rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    @media print {
        body { background: #fff; }
        .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; }
        .no-print { display: none !important; }
    }
</style>
