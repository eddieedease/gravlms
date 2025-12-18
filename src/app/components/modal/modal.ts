import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
    selector: 'app-modal',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './modal.html'
})
export class ModalComponent {
    @Input() isOpen = false;
    @Input() title = '';
    @Input() maxWidth: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full' = 'md';
    @Output() close = new EventEmitter<void>();

    onClose() {
        this.close.emit();
    }

    // Prevent closing when clicking inside the modal content
    onContentClick(event: MouseEvent) {
        event.stopPropagation();
    }
}
