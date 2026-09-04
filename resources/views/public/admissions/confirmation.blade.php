<x-guest-layout width="sm:max-w-xl" title="Application Submitted">
    <div class="py-4 text-center">
        <!-- Success icon -->
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100">
            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h2 class="mt-4 text-2xl font-bold text-gray-800">Application Submitted Successfully</h2>
        <p class="mt-2 text-sm text-gray-600">
            Thank you. Your admission application has been received. Please keep your
            application number safe — you will need it for any follow up.
        </p>
    </div>

    <!-- Application summary -->
    <div class="mt-2 border border-gray-200 rounded-lg divide-y divide-gray-200">
        <div class="px-4 py-3 flex items-center justify-between">
            <span class="text-sm font-medium text-gray-500">Application Number</span>
            <span class="text-base font-bold text-gray-900">{{ $confirmation['application_number'] }}</span>
        </div>
        <div class="px-4 py-3 flex items-center justify-between">
            <span class="text-sm font-medium text-gray-500">Student Name</span>
            <span class="text-base font-medium text-gray-900">{{ $confirmation['student_name'] }}</span>
        </div>
        <div class="px-4 py-3 flex items-center justify-between">
            <span class="text-sm font-medium text-gray-500">Current Status</span>
            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                {{ $confirmation['status'] }}
            </span>
        </div>
    </div>

    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <p class="text-sm text-blue-800">
            Your application is <span class="font-medium">Pending</span> review. The school will
            contact you on the mobile number you provided with the next steps.
        </p>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex items-center justify-center space-x-3">
        <a
            href="{{ url('/') }}"
            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
        >
            Back to Home
        </a>
        <a
            href="{{ route('public.admissions.apply') }}"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
            Submit Another Application
        </a>
    </div>
</x-guest-layout>
