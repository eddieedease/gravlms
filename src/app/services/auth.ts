import { Injectable, signal } from '@angular/core';


@Injectable({
  providedIn: 'root',
})
export class Auth {
  readonly currentUser = signal<{ name: string; role: 'admin' | 'user' } | null>(null);

  login(role: 'admin' | 'user' = 'user') {
    this.currentUser.set({ name: 'Test User', role });
  }

  logout() {
    this.currentUser.set(null);
  }
}

