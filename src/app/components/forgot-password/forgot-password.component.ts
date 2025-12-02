import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { TranslateModule } from '@ngx-translate/core';

@Component({
    selector: 'app-forgot-password',
    standalone: true,
    imports: [CommonModule, ReactiveFormsModule, RouterLink, TranslateModule],
    template: `
    <div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8 bg-gray-50">
      <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="mt-10 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900">Reset your password</h2>
      </div>

      <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm card p-8">
        <form class="space-y-6" [formGroup]="form" (ngSubmit)="onSubmit()">
          <div>
            <label for="email" class="block text-sm font-medium leading-6 text-gray-900">{{ 'EMAIL_ADDRESS' | translate }}</label>
            <div class="mt-2">
              <input id="email" type="email" formControlName="email" required class="input-field">
            </div>
            <div *ngIf="form.get('email')?.touched && form.get('email')?.invalid" class="text-red-600 text-xs mt-1">
              Please enter a valid email address.
            </div>
          </div>

          <div *ngIf="message" class="text-green-600 text-sm mt-2 bg-green-50 p-2 rounded border border-green-200">
            {{ message }}
          </div>

          <div *ngIf="errorMessage" class="text-red-600 text-sm mt-2 bg-red-50 p-2 rounded border border-red-200">
            {{ errorMessage }}
          </div>

          <div>
            <button type="submit" [disabled]="form.invalid || isLoading"
              class="flex w-full justify-center btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
              {{ isLoading ? 'Sending...' : 'Send Reset Link' }}
            </button>
          </div>

          <div class="text-sm text-center">
            <a routerLink="/login" class="font-semibold text-indigo-600 hover:text-indigo-500">Back to Login</a>
          </div>
        </form>
      </div>
    </div>
  `
})
export class ForgotPasswordComponent {
    form: FormGroup;
    message: string = '';
    errorMessage: string = '';
    isLoading: boolean = false;
    private authService = inject(AuthService);
    private fb = inject(FormBuilder);

    constructor() {
        this.form = this.fb.group({
            email: ['', [Validators.required, Validators.email]]
        });
    }

    onSubmit() {
        if (this.form.valid) {
            this.isLoading = true;
            this.message = '';
            this.errorMessage = '';

            this.authService.forgotPassword(this.form.value.email).subscribe({
                next: (res: any) => {
                    this.message = res.message;
                    this.isLoading = false;
                    this.form.reset();
                },
                error: (err) => {
                    this.errorMessage = err.error?.error || 'An error occurred. Please try again.';
                    this.isLoading = false;
                }
            });
        }
    }
}
