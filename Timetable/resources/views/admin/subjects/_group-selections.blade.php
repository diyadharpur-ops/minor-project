@php
    $classOptions = ['A', 'B', 'C'];
    $savedClasses = isset($subject)
        ? $subject->effective_class_selections
        : [];
    $selectedClasses = old('class_selections', $savedClasses);
    $savedDivisions = isset($subject) ? ($subject->division_selections ?? []) : [];
    $savedTutorials = isset($subject) ? ($subject->tutorial_selections ?? []) : [];
    $selectedClasses = is_array($selectedClasses) ? $selectedClasses : [];
    $selectedDivisions = old('division_selections', $savedDivisions);
    $selectedTutorials = old('tutorial_selections', $savedTutorials);
    $selectedDivisions = is_array($selectedDivisions) ? $selectedDivisions : [];
    $selectedTutorials = is_array($selectedTutorials) ? $selectedTutorials : [];
@endphp

<style>
    .subject-selection-section { margin: 18px 0; }
    .subject-selection-section > strong { display: block; margin-bottom: 8px; }
    .subject-selection-groups { display: grid; gap: 10px; }
    .subject-selection-group { padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; }
    .subject-selection-group[hidden] { display: none; }
    .subject-selection-group > strong { display: block; margin-bottom: 8px; }
    .subject-selection-options { display: flex; flex-wrap: wrap; gap: 8px; }
    .subject-selection-option { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 999px; background: white; }
    .subject-calculation-preview { display: grid; gap: 6px; margin-top: 18px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; }
</style>

<input type="hidden" name="selection_groups_present" value="1" />

<div class="subject-selection-section">
    <strong>Class Selection:</strong>
    <div class="subject-selection-options">
        @foreach ($classOptions as $class)
            <label class="subject-selection-option">
                <input type="checkbox" class="subject-class-option" name="class_selections[]" value="{{ $class }}" {{ in_array($class, $selectedClasses, true) ? 'checked' : '' }} />
                <span>{{ $class }}</span>
            </label>
        @endforeach
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-subject-selection-form]').forEach(function (form) {
            function updateCalculationPreview() {
                const countChecked = function (selector) {
                    return form.querySelectorAll(selector + ':checked').length;
                };
                const creditValue = function (name) {
                    return Number(form.querySelector('[name="' + name + '"]').value) || 0;
                };
                const lectureCredits = creditValue('lecture_credit') * countChecked('.subject-class-option');
                const labCredits = creditValue('lab_credit') * countChecked('input[name="division_selections[]"]');
                const tutorialCredits = creditValue('tutorial_credit') * countChecked('input[name="tutorial_selections[]"]');

                const values = {
                    'lecture-credits': lectureCredits,
                    'lecture-hours': lectureCredits,
                    'lab-credits': labCredits,
                    'lab-hours': labCredits * 2,
                    'tutorial-credits': tutorialCredits,
                    'tutorial-hours': tutorialCredits,
                    'total-hours': lectureCredits + labCredits * 2 + tutorialCredits
                };

                Object.keys(values).forEach(function (field) {
                    const output = form.querySelector('[data-calculated-field="' + field + '"]');
                    if (output) {
                        output.textContent = String(values[field]);
                    }
                });
            }

            form.addEventListener('change', function (event) {
                if (event.target.matches('.subject-class-option')) {
                    const className = event.target.value;
                    const classIsSelected = event.target.checked;

                    form.querySelectorAll('[data-selection-class-group="' + className + '"]').forEach(function (group) {
                        group.hidden = !classIsSelected;

                        if (!classIsSelected) {
                            group.querySelectorAll('input[type="checkbox"]').forEach(function (option) {
                                option.checked = false;
                            });
                        }
                    });
                }

                updateCalculationPreview();
            });
            form.addEventListener('input', updateCalculationPreview);
            updateCalculationPreview();
        });
    });
</script>
<div class="subject-selection-section">
    <strong>Division Selection:</strong>
    <div class="subject-selection-groups">
        @foreach ($classOptions as $class)
            <div class="subject-selection-group" data-selection-class-group="{{ $class }}" {{ in_array($class, $selectedClasses, true) ? '' : 'hidden' }}>
                <strong>Class {{ $class }}</strong>
                <div class="subject-selection-options">
                    @foreach (range(1, 4) as $number)
                        @php $division = $class.$number; @endphp
                        <label class="subject-selection-option">
                            <input type="checkbox" name="division_selections[]" value="{{ $division }}" {{ in_array($division, $selectedDivisions, true) ? 'checked' : '' }} />
                            <span>{{ $division }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="subject-selection-section">
    <strong>Tutorial Selection:</strong>
    <div class="subject-selection-groups">
        @foreach ($classOptions as $class)
            <div class="subject-selection-group" data-selection-class-group="{{ $class }}" {{ in_array($class, $selectedClasses, true) ? '' : 'hidden' }}>
                <strong>Class {{ $class }}</strong>
                <div class="subject-selection-options">
                    @foreach (range(1, 4) as $number)
                        @php $tutorial = $class.'T'.$number; @endphp
                        <label class="subject-selection-option">
                            <input type="checkbox" name="tutorial_selections[]" value="{{ $tutorial }}" {{ in_array($tutorial, $selectedTutorials, true) ? 'checked' : '' }} />
                            <span>{{ $tutorial }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-subject-selection-form]').forEach(function (form) {
            form.querySelectorAll('.subject-class-option').forEach(function (classOption) {
                classOption.addEventListener('change', function () {
                    const className = this.value;
                    const classIsSelected = this.checked;

                    form.querySelectorAll('[data-selection-class-group="' + className + '"]').forEach(function (group) {
                        group.hidden = !classIsSelected;

                        if (!classIsSelected) {
                            group.querySelectorAll('input[type="checkbox"]').forEach(function (option) {
                                option.checked = false;
                            });
                        }
                    });
                });
            });
        });
    });
</script>
