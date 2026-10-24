@php
    $classOptions = ['A', 'B', 'C'];
    $savedClasses = isset($subject)
        ? ($subject->class_selections ?? ($subject->division?->name ? [$subject->division->name] : $classOptions))
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
