import { Component, OnInit, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { AuthService } from '../../services/auth.service';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-landing',
  imports: [TranslateModule, FormsModule],
  templateUrl: './landing.html',
  styleUrl: './landing.css',
})
export class Landing implements OnInit {
  auth = inject(AuthService);
  router = inject(Router);

  tenantSlug = signal('');

  ngOnInit() {
    if (this.auth.currentUser()) {
      this.router.navigate(['/dashboard']);
    }
  }

  goToLogin() {
    const slug = this.tenantSlug().trim();
    if (slug) {
      this.router.navigate(['/login', slug]);
    }
  }
}
