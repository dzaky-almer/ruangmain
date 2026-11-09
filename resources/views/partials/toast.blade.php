@if (session('sukses'))
    <div class="pointer-events-none fixed inset-x-4 top-4 z-50 mx-auto flex max-w-md items-start gap-3 rounded-xl border-2 border-ok bg-mint px-4 py-3 text-sm font-bold text-ink shadow-[3px_3px_0_#252525]" role="status" data-toast-sukses>
        <span aria-hidden="true">✓</span>
        <p class="flex-1">{{ session('sukses') }}</p>
        <button class="pointer-events-auto font-extrabold" type="button" aria-label="Tutup" data-toast-tutup>&times;</button>
    </div>
    <script>
        (() => {
            const toast = document.querySelector('[data-toast-sukses]');
            if (!toast) return;
            const tutup = () => toast.remove();
            toast.querySelector('[data-toast-tutup]').addEventListener('click', tutup);
            setTimeout(tutup, 6000);
        })();
    </script>
@endif