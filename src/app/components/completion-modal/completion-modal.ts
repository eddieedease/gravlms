import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-completion-modal',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div *ngIf="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
      
      <!-- Modal container -->
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
          <div>
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
              <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path>
              </svg>
            </div>
            <div class="mt-3 text-center sm:mt-5">
              <h3 class="text-base font-semibold leading-6 text-gray-900">
                Congratulations!
              </h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500">
                  @if (isLti) {
                    You have successfully completed <strong>{{ courseTitle }}</strong>. Your grade has been sent to the LMS.
                  } @else {
                    You have successfully completed the course <strong>{{ courseTitle }}</strong>.
                  }
                </p>
              </div>
            </div>
          </div>
          <div class="mt-5 sm:mt-6">
            <button type="button" (click)="onDashboard()" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
              {{ isLti ? 'Return to LMS' : 'Back to Dashboard' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  `
})
export class CompletionModalComponent {
  @Input() isOpen = false;
  @Input() courseTitle = '';
  @Input() isLti = false;
  @Output() close = new EventEmitter<void>();
  @Output() navigateDashboard = new EventEmitter<void>();

  onClose() {
    this.close.emit();
  }

  onDashboard() {
    this.navigateDashboard.emit();
  }
}
