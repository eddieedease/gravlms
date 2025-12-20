import { Component, Input, signal, computed, forwardRef, ElementRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';
import { TranslateModule } from '@ngx-translate/core';

@Component({
    selector: 'app-autocomplete',
    standalone: true,
    imports: [CommonModule, TranslateModule],
    templateUrl: './autocomplete.html',
    providers: [
        {
            provide: NG_VALUE_ACCESSOR,
            useExisting: forwardRef(() => AutocompleteComponent),
            multi: true
        }
    ]
})
export class AutocompleteComponent implements ControlValueAccessor {
    @Input() data: any[] = [];
    @Input() displayKey: any = 'name';
    @Input() valueKey: any = 'id';
    @Input() secondaryKey?: string; // Optional: e.g. show email alongside username
    @Input() placeholder: any = 'Select...';

    value = signal<any>(null);
    isOpen = signal(false);
    searchTerm = signal('');
    disabled = false;
    hasError = false;

    displayValue = computed(() => {
        const val = this.value();
        if (!val) return this.searchTerm();

        // If we have a selected value, we want to show its display text
        // BUT if the user is typing (searching), we show what they type.
        // This is tricky. Simplified approach:
        // If isOpen is true, show searchTerm.
        // If isOpen is false, show selected item's text.

        if (this.isOpen()) {
            return this.searchTerm();
        }

        const selectedItem = this.data.find(d => d[this.valueKey] == val);
        return selectedItem ? selectedItem[this.displayKey] : '';
    });

    filteredData = computed(() => {
        const term = this.searchTerm().toLowerCase();
        return this.data.filter(item => {
            const main = String(item[this.displayKey] || '').toLowerCase();
            const secondary = this.secondaryKey ? String(item[this.secondaryKey] || '').toLowerCase() : '';
            return main.includes(term) || secondary.includes(term);
        });
    });

    onChange: any = () => { };
    onTouched: any = () => { };

    onInput(event: Event) {
        const target = event.target as HTMLInputElement;
        this.searchTerm.set(target.value);
        this.isOpen.set(true);

        // If user clears input, clear value
        if (!target.value) {
            this.value.set(null);
            this.onChange(null);
        }
    }

    open() {
        if (this.disabled) return;
        this.isOpen.set(true);
        // Initialize search term with current display value if starting fresh search?
        // Or keep empty to show all? Let's keep existing logic or reset.
        // Better UX: clicking input shows full list if empty, or current search.
        // If we have a value selected, when clicking to edit, maybe we want to search anew?
        // Let's reset search term on open if it was just displaying the static value.
        if (this.value()) {
            // Optionally pre-fill search with current name
            // this.searchTerm.set(this.displayValue()); 
            // Or clear it to allow fresh search:
            this.searchTerm.set('');
        } else {
            this.searchTerm.set('');
        }
    }

    onBlur() {
        // Delay closing to allow click event on options to fire
        setTimeout(() => {
            this.isOpen.set(false);
            this.onTouched();

            // If we blurred without selecting a valid option, revert to previous value or clear?
            // Strict mode: if term doesn't match a selected item, reset.
            if (!this.value()) {
                this.searchTerm.set('');
            }
        }, 200);
    }

    select(item: any) {
        const val = item[this.valueKey];
        this.value.set(val);
        this.onChange(val);
        this.isOpen.set(false);
        this.searchTerm.set(''); // Reset search term so displayValue computed takes over
    }

    clear(event: Event) {
        event.stopPropagation();
        this.value.set(null);
        this.searchTerm.set('');
        this.onChange(null);
        this.onTouched();
    }

    isActive(item: any): boolean {
        return item[this.valueKey] === this.value();
    }

    writeValue(value: any): void {
        this.value.set(value);
    }

    registerOnChange(fn: any): void {
        this.onChange = fn;
    }

    registerOnTouched(fn: any): void {
        this.onTouched = fn;
    }

    setDisabledState(isDisabled: boolean): void {
        this.disabled = isDisabled;
    }
}
