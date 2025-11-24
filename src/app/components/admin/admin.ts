import { Component, inject, OnInit, signal } from '@angular/core';
import { UserService } from '../../services/user.service';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { DatePipe } from '@angular/common';

@Component({
  selector: 'app-admin',
  imports: [ReactiveFormsModule, DatePipe],
  templateUrl: './admin.html',
  styleUrl: './admin.css',
})
export class Admin implements OnInit {
  private userService = inject(UserService);
  private fb = inject(FormBuilder);

  users = signal<any[]>([]);
  isEditing = signal(false);
  showForm = signal(false);
  currentUserId: number | null = null;

  form = this.fb.group({
    username: ['', Validators.required],
    password: [''], // Optional on edit
    role: ['editor', Validators.required]
  });

  ngOnInit() {
    this.loadUsers();
  }

  loadUsers() {
    this.userService.getUsers().subscribe(users => {
      this.users.set(users);
    });
  }

  startCreate() {
    this.isEditing.set(false);
    this.currentUserId = null;
    this.form.reset({ role: 'editor' });
    this.form.controls.password.addValidators(Validators.required);
    this.showForm.set(true);
  }

  startEdit(user: any) {
    this.isEditing.set(true);
    this.currentUserId = user.id;
    this.form.patchValue({
      username: user.username,
      role: user.role
    });
    this.form.controls.password.removeValidators(Validators.required);
    this.form.controls.password.updateValueAndValidity();
    this.showForm.set(true);
  }

  cancel() {
    this.showForm.set(false);
    this.form.reset();
  }

  onSubmit() {
    if (this.form.valid) {
      const user = this.form.value;
      if (this.isEditing() && this.currentUserId) {
        this.userService.updateUser(this.currentUserId, user).subscribe(() => {
          this.loadUsers();
          this.cancel();
        });
      } else {
        this.userService.createUser(user).subscribe(() => {
          this.loadUsers();
          this.cancel();
        });
      }
    }
  }

  deleteUser(id: number) {
    if (confirm('Are you sure?')) {
      this.userService.deleteUser(id).subscribe(() => {
        this.loadUsers();
      });
    }
  }
}
