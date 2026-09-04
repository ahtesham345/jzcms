<footer class="bg-white border-t border-gray-200 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col md:flex-row justify-between items-center text-sm text-gray-600">
            <div class="mb-2 md:mb-0">
                <p>&copy; {{ date('Y') }} {{ \App\Models\Setting::current()->brandName() }}. All rights reserved.</p>
            </div>
            <div>
                <p>Version 1.0</p>
            </div>
        </div>
    </div>
</footer>
