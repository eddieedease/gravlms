import { Component, inject } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { OrganisationService } from '../../services/organisation.service';

import { TranslateModule } from '@ngx-translate/core';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, TranslateModule, RouterLink],
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  public readonly orgService = inject(OrganisationService);
  errorMessage = '';

  form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required]
  });

  onSubmit() {
    if (this.form.valid) {
      this.auth.login(this.form.value).subscribe({
        next: () => {
          this.router.navigate(['/']);
        },
        error: (err: any) => {
          this.errorMessage = 'Invalid credentials';
        }
      });
    }
  }
}
