<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>SIMS - Fleet Management System</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="antialiased selection:bg-indigo-500 selection:text-white">
    <div class="bg-white dark:bg-gray-900">
        <nav class="w-full bg-white/90 dark:bg-gray-900/90 backdrop-blur shadow-md border-b border-gray-200/20 dark:border-white/10 sticky top-0 z-50">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 flex items-center justify-between h-16">
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">SIMS</span>
                </div>
                <div>
                    <a href="/login" class="text-md font-medium text-neutral-500 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-white transition-colors">Login →</a>
                </div>
            </div>
        </nav>

        <main>
            <div class="relative isolate overflow-hidden flex flex-col min-h-[90vh] justify-center">
                <svg aria-hidden="true" class="absolute inset-0 -z-10 size-full mask-[radial-gradient(100%_100%_at_top_right,white,transparent)] stroke-gray-200 dark:stroke-white/10">
                    <defs>
                        <pattern id="grid" width="200" height="200" x="50%" y="-1" patternUnits="userSpaceOnUse">
                            <path d="M.5 200V.5H200" fill="none" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>

                <div class="mx-auto max-w-7xl px-6 pt-10 pb-24 sm:pb-32 lg:flex lg:px-8 lg:py-20 items-center">
                    <div class="mx-auto max-w-2xl shrink-0 lg:mx-0 lg:pt-8">
                        <div class="inline-flex space-x-6 mb-6">
                            <span class="rounded-full bg-indigo-600/10 px-3 py-1 text-sm font-semibold leading-6 text-indigo-600 ring-1 ring-inset ring-indigo-600/10">Sprint 4 Reboot</span>
                        </div>
                        <h1 class="text-5xl font-semibold tracking-tight text-gray-900 sm:text-7xl dark:text-white">
                            Manage your fleet anytime, anywhere.
                        </h1>
                        <p class="mt-8 text-lg font-medium text-gray-500 sm:text-xl/8 dark:text-gray-400">
                            The all-in-one solution for modern logistics. Real-time GPS tracking, automated driver alerts, and secure multi-tenant data isolation.
                        </p>
                        <div class="mt-10 flex items-center gap-x-6">
                            <a href="#video" class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all">Watch Commercial</a>
                            <a href="/login" class="text-sm font-semibold leading-6 text-gray-900 dark:text-white underline decoration-indigo-500 decoration-2">Access Dashboard <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                    
                    <div class="mx-auto mt-16 flex max-w-2xl sm:mt-24 lg:mt-0 lg:mr-0 lg:ml-10 lg:max-w-none lg:flex-none xl:ml-32">
                        <div class="max-w-3xl flex-none sm:max-w-5xl lg:max-w-none">
                            <img src="https://tailwindcss.com/plus-assets/img/component-images/project-app-screenshot.png" alt="SIMS Dashboard" class="w-[48rem] rounded-md bg-white/5 shadow-2xl ring-1 ring-white/10" />
                        </div>
                    </div>
                </div>
            </div>

            <div id="features" class="mx-auto mt-12 max-w-7xl px-6 lg:px-8 py-24">
                <div class="mx-auto max-w-2xl lg:text-center">
                    <h2 class="text-base font-semibold text-indigo-600 dark:text-indigo-400">Professional Logistics</h2>
                    <p class="mt-2 text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl dark:text-white">Everything you need to scale</p>
                </div>
                
                <div class="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
                    <dl class="grid max-w-xl grid-cols-1 gap-x-8 gap-y-16 lg:max-w-none lg:grid-cols-3">
                        <div class="flex flex-col">
                            <dt class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-lg bg-indigo-600">
                                    <svg class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                IoT Integration
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base text-gray-600 dark:text-gray-400">
                                <p class="flex-auto">Real-time tracking with Raspberry Pi and GPS sensors.</p>
                            </dd>
                        </div>

                        <div class="flex flex-col">
                            <dt class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-lg bg-indigo-600">
                                    <svg class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                Multi-tenant Architecture
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base text-gray-600 dark:text-gray-400">
                                <p class="flex-auto">Total data isolation with dedicated databases per client.</p>
                            </dd>
                        </div>

                        <div class="flex flex-col">
                            <dt class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-lg bg-indigo-600">
                                    <svg class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                High Performance
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base text-gray-600 dark:text-gray-400">
                                <p class="flex-auto">Lightning-fast SPA dashboard built with Vue 3.</p>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div id="video" class="bg-gray-50 dark:bg-gray-800/50 py-24 sm:py-32">
                <div class="mx-auto max-w-7xl px-6 lg:px-8 text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">See SIMS in action</h2>
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-400 mb-12">Watch our commercial film to understand how we are changing fleet management.</p>
                    
                    <div class="relative max-w-4xl mx-auto rounded-2xl overflow-hidden shadow-2xl border-4 border-indigo-600/20">
                        <div class="aspect-video bg-black flex items-center justify-center">
                            <p class="text-white/50 italic text-sm text-center px-4">
                                [ Insert your Commercial Video here ] <br>
                                (English audio with Catalan/Spanish subtitles)
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <footer class="mx-auto max-w-7xl px-6 lg:px-8 border-t border-gray-200 dark:border-white/10 mt-20">
            <div class="py-12 md:flex md:items-center md:justify-between">
                <div class="flex justify-center gap-x-6 md:order-2">
                    <a href="https://github.com/orgs/Sprint4-ProjectSIMS-Team1" class="text-gray-400 hover:text-indigo-500 transition-colors">
                        <span class="sr-only">GitHub</span>
                        <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                    </a>
                </div>
                <p class="mt-8 text-center text-sm text-gray-500 md:order-1 md:mt-0">&copy; 2026 SIMS Project Team 1. EUPL Licensed.</p>
            </div>
        </footer>
    </div>
</body>
</html>