@extends('layouts.app')

@section('page-title', 'Nova Prova')

@section('content')
    <style>
        .config-card {
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 700px;
            margin: 0 auto;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .form-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1e293b;
        }

        .radio-group {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .radio-option {
            flex: 1;
            position: relative;
        }

        .radio-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .radio-label {
            display: block;
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .radio-option input[type="radio"]:checked+.radio-label {
            border-color: #2563EB;
            background: #eff6ff;
        }

        .radio-label h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .radio-label p {
            font-size: 13px;
            color: #64748b;
        }

        .slider-container {
            margin-bottom: 24px;
        }

        .slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #e2e8f0;
            outline: none;
            -webkit-appearance: none;
        }

        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #2563EB;
            cursor: pointer;
        }

        .slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #2563EB;
            cursor: pointer;
            border: none;
        }

        .slider-value {
            font-size: 24px;
            font-weight: 700;
            color: #2563EB;
            text-align: center;
            margin-top: 12px;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .checkbox-option:hover {
            border-color: #cbd5e1;
        }

        .checkbox-option input[type="checkbox"]:checked~label {
            color: #2563EB;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #2563EB;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .subject-distribution {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }

        .subject-input label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #334155;
        }

        .subject-input input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 15px;
        }

        /* DARK MODE */
        :root.dark .config-card {
            background: #1e293b;
            color: #f1f5f9;
        }

        :root.dark .form-section h3 {
            color: #f1f5f9;
        }

        :root.dark .radio-label {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(15, 23, 42, 0.3);
        }

        :root.dark .radio-label h4 {
            color: #cbd5e1;
        }

        :root.dark .radio-label p {
            color: #64748b;
        }

        :root.dark .radio-option input[type="radio"]:checked+.radio-label {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.15);
        }

        :root.dark .radio-option input[type="radio"]:checked+.radio-label h4 {
            color: #93c5fd;
        }

        :root.dark .slider {
            background: #334155;
        }

        :root.dark .slider::-webkit-slider-thumb {
            background: #3b82f6;
        }

        :root.dark .slider-value {
            color: #60a5fa;
        }

        :root.dark .checkbox-option {
            border-color: rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
        }

        :root.dark .checkbox-option:hover {
            border-color: rgba(255, 255, 255, 0.2);
        }

        :root.dark .subject-input label {
            color: #cbd5e1;
        }

        :root.dark .subject-input input {
            background: #0f172a;
            border-color: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
        }
    </style>

    <div class="config-card">
        <form method="POST" action="{{ route('simulations.store') }}" id="simulationForm">
            @csrf

            <!-- Exibir Erros de Validação ou Exceções -->
            @if ($errors->any())
                <div
                    style="background-color: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 12px; border-radius: 8px; margin-bottom: 24px;">
                    <ul style="list-style-type: disc; padding-left: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-section">
                <h3>Tipo de Prova</h3>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="type_enem" name="type" value="enem" checked onchange="updateConfig()">
                        <label for="type_enem" class="radio-label">
                            <h4>ENEM</h4>
                            <p>90 questões fixas</p>
                        </label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="type_concurso" name="type" value="concurso" onchange="updateConfig()">
                        <label for="type_concurso" class="radio-label">
                            <h4>Concurso</h4>
                            <p>Personalizável</p>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-section" id="questionConfig">
                <h3>Configuração de Questões</h3>

                <div id="enemConfig">
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="enem_mixed" name="enem_mode" value="mixed" checked
                                onchange="updateDistribution()">
                            <label for="enem_mixed" class="radio-label">
                                <h4>Mista</h4>
                                <p>45 Mat + 45 Port</p>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="enem_math" name="enem_mode" value="math"
                                onchange="updateDistribution()">
                            <label for="enem_math" class="radio-label">
                                <h4>Só Matemática</h4>
                                <p>90 questões</p>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="enem_portuguese" name="enem_mode" value="portuguese"
                                onchange="updateDistribution()">
                            <label for="enem_portuguese" class="radio-label">
                                <h4>Só Português</h4>
                                <p>90 questões</p>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="concursoConfig" style="display: none;">
                    <div class="slider-container">
                        <label>Total de Questões</label>
                        <input type="range" min="40" max="100" value="60" class="slider" id="questionSlider"
                            oninput="updateSliderValue(this.value)">
                        <div class="slider-value" id="sliderValue">60 questões</div>
                    </div>

                    <div class="subject-distribution">
                        <div class="subject-input">
                            <label>Matemática</label>
                            <input type="number" id="math_count" min="0" max="100" value="30"
                                onchange="validateDistribution()">
                        </div>
                        <div class="subject-input">
                            <label>Português</label>
                            <input type="number" id="portuguese_count" min="0" max="100" value="30"
                                onchange="validateDistribution()">
                        </div>
                    </div>

                    <!-- Filtros Opcionais -->
                    <div style="margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
                        <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #334155;">Filtros do Edital (Opcional)</h4>
                        
                        <div style="display: grid; gap: 16px;">
                            <div class="subject-input">
                                <label>Banca (Segure Ctrl para selecionar várias)</label>
                                <select name="organization[]" multiple style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; height: 120px;">
                                    @foreach($organizations as $org)
                                        <option value="{{ $org }}">{{ $org }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="subject-input">
                                <label>Órgão</label>
                                <select name="institution[]" multiple style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; height: 120px;">
                                    @foreach($institutions as $inst)
                                        <option value="{{ $inst }}">{{ $inst }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="subject-input">
                                <label>Cargo</label>
                                <select name="role[]" multiple style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; height: 120px;">
                                    @foreach($roles as $role)
                                        <option value="{{ $role }}">{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="total_questions" id="total_questions" value="90">
                <input type="hidden" name="subject_distribution[Matemática]" id="math_hidden" value="45">
                <input type="hidden" name="subject_distribution[Português]" id="portuguese_hidden" value="45">
            </div>

            <div class="form-section">
                <label class="checkbox-option">
                    <input type="checkbox" name="include_essay" value="1">
                    <label for="include_essay">
                        <strong>Incluir Redação</strong>
                        <p style="font-size: 13px; color: #64748b; margin-top:4px;">Disponível para planos Básico e Plus</p>
                    </label>
                </label>
            </div>

            <button type="submit" class="btn-submit">Iniciar Prova</button>
        </form>
    </div>

    <script>
        function updateConfig() {
            const isEnem = document.getElementById('type_enem').checked;
            document.getElementById('enemConfig').style.display = isEnem ? 'block' : 'none';
            document.getElementById('concursoConfig').style.display = isEnem ? 'none' : 'block';

            if (isEnem) {
                updateDistribution();
            } else {
                updateSliderValue(60);
            }
        }

        function updateDistribution() {
            const mode = document.querySelector('input[name="enem_mode"]:checked').value;

            let math = 45, port = 45;

            if (mode === 'math') {
                math = 90;
                port = 0;
            } else if (mode === 'portuguese') {
                math = 0;
                port = 90;
            }

            document.getElementById('total_questions').value = 90;
            document.getElementById('math_hidden').value = math;
            document.getElementById('portuguese_hidden').value = port;
        }

        function updateSliderValue(value) {
            document.getElementById('sliderValue').textContent = value + ' questões';
            document.getElementById('total_questions').value = value;

            const math = parseInt(document.getElementById('math_count').value);
            const port = parseInt(document.getElementById('portuguese_count').value);

            document.getElementById('math_hidden').value = math;
            document.getElementById('portuguese_hidden').value = port;
        }

        function validateDistribution() {
            const total = parseInt(document.getElementById('questionSlider').value);
            const math = parseInt(document.getElementById('math_count').value);
            const port = parseInt(document.getElementById('portuguese_count').value);

            if (math + port !== total) {
                const diff = total - (math + port);
                document.getElementById('portuguese_count').value = port + diff;
            }

            updateSliderValue(total);
        }
    </script>
@endsection