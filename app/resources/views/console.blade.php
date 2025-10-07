<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Self AI') }} Console</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                :root {
                    color-scheme: light dark;
                }

                body {
                    font-family: 'Instrument Sans', sans-serif;
                    margin: 0;
                    background: #0f172a;
                    color: #f8fafc;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 1.5rem;
                }

                main {
                    width: min(960px, 100%);
                    background: rgba(15, 23, 42, 0.85);
                    border-radius: 1.5rem;
                    padding: 2.5rem;
                    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.9);
                    backdrop-filter: blur(12px);
                }

                h1 {
                    font-size: clamp(2rem, 5vw, 2.75rem);
                    margin-bottom: 0.5rem;
                }

                p.description {
                    margin-top: 0;
                    margin-bottom: 2rem;
                    color: rgba(226, 232, 240, 0.85);
                }

                #console-app {
                    display: grid;
                    gap: 1.5rem;
                }

                .panel {
                    border: 1px solid rgba(148, 163, 184, 0.25);
                    border-radius: 1rem;
                    padding: 1.5rem;
                    background: rgba(30, 41, 59, 0.75);
                }

                label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 0.75rem;
                }

                input[type="text"],
                input[type="url"],
                textarea {
                    width: 100%;
                    border-radius: 0.75rem;
                    border: 1px solid rgba(148, 163, 184, 0.4);
                    background: rgba(15, 23, 42, 0.65);
                    color: inherit;
                    padding: 0.75rem 1rem;
                    font-size: 1rem;
                    resize: vertical;
                }

                button {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    border-radius: 9999px;
                    border: none;
                    padding: 0.65rem 1.25rem;
                    font-weight: 600;
                    cursor: pointer;
                    background: linear-gradient(120deg, #22d3ee, #6366f1);
                    color: #0f172a;
                }

                pre {
                    background: rgba(15, 23, 42, 0.65);
                    border-radius: 1rem;
                    padding: 1rem;
                    font-size: 0.9rem;
                    overflow: auto;
                    max-height: 320px;
                }
            </style>
        @endif
    </head>
    <body>
        <main>
            <h1>Self AI Operator Console</h1>
            <p class="description">
                Use this lightweight console to call the platform APIs for health checks, chat completions, memory searches, and document ingestion.
                Provide an API token that has access to the relevant scopes to issue authenticated requests.
            </p>
            <div id="console-app"
                data-endpoints='@json($apiEndpoints)'
                data-default-token=""
                class="console-grid">
                <noscript>This console requires JavaScript.</noscript>
            </div>
        </main>
    </body>
</html>
