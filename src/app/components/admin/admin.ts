import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { UserService } from '../../services/user.service';
import { CourseService } from '../../services/course.service';
import { LearningService } from '../../services/learning.service';
import { GroupsService } from '../../services/groups.service';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { LtiManagementComponent } from './lti-management/lti-management';
import { ModalComponent } from '../modal/modal';
import { ApiService } from '../../services/api.service';

import { TranslateModule } from '@ngx-translate/core';

@Component({
  selector: 'app-admin',
  imports: [ReactiveFormsModule, DatePipe, LtiManagementComponent, TranslateModule, ModalComponent],
  templateUrl: './admin.html',
  styleUrl: './admin.css',
})
export class Admin implements OnInit {
  private userService = inject(UserService);
  private courseService = inject(CourseService);
  private learningService = inject(LearningService);
  private groupsService = inject(GroupsService);
  private apiService = inject(ApiService);
  private fb = inject(FormBuilder);

  users = signal<any[]>([]);
  courses = signal<any[]>([]);
  groups = signal<any[]>([]);
  ltiTools = signal<any[]>([]);

  // Search State
  searchTerm = signal('');

  filteredUsers = computed(() => {
    const term = this.searchTerm().toLowerCase();
    return this.users().filter(user =>
      user.username.toLowerCase().includes(term) ||
      user.email.toLowerCase().includes(term)
    );
  });

  filteredGroups = computed(() => {
    const term = this.searchTerm().toLowerCase();
    return this.groups().filter(group =>
      group.name.toLowerCase().includes(term) ||
      (group.description && group.description.toLowerCase().includes(term))
    );
  });

  // Tab State
  activeTab = signal<'users' | 'groups' | 'courses' | 'lti' | 'email'>('users');

  // User Management State
  isEditing = signal(false);
  showForm = signal(false);
  currentUserId: number | null = null;

  // Group Management State
  showGroupForm = signal(false);
  isEditingGroup = signal(false);
  currentGroupId: number | null = null;
  selectedGroup: any = null;
  groupUsers = signal<any[]>([]);
  groupCourses = signal<any[]>([]);
  groupMonitors = signal<any[]>([]);

  // Email State
  testEmailControl = this.fb.control('', [Validators.required, Validators.email]);
  isSendingEmail = signal(false);
  emailStatus = signal<{ success: boolean; message: string } | null>(null);

  form = this.fb.group({
    username: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    password: [''], // Optional on edit
    role: ['editor', Validators.required]
  });

  assignForm = this.fb.group({
    userId: ['', Validators.required],
    courseId: ['', Validators.required]
  });

  groupForm = this.fb.group({
    name: ['', Validators.required],
    description: ['']
  });

  assignGroupUserForm = this.fb.group({
    userId: ['', Validators.required]
  });

  assignGroupMonitorForm = this.fb.group({
    userId: ['', Validators.required]
  });

  assignGroupCourseForm = this.fb.group({
    courseId: ['', Validators.required],
    validityDays: ['']
  });

  assignedCourses = signal<any[]>([]);

  ngOnInit() {
    this.loadUsers();
    this.loadCourses();
    this.loadGroups();

    // Listen to user selection changes
    this.assignForm.controls.userId.valueChanges.subscribe(userId => {
      if (userId) {
        this.loadAssignedCourses(Number(userId));
      } else {
        this.assignedCourses.set([]);
      }
    });
  }

  loadUsers() {
    this.userService.getUsers().subscribe(users => {
      this.users.set(users);
    });
  }

  loadCourses() {
    this.courseService.getCourses().subscribe(courses => {
      this.courses.set(courses);
    });
  }

  loadGroups() {
    this.groupsService.getGroups().subscribe(groups => {
      this.groups.set(groups);
    });
  }

  loadAssignedCourses(userId: number) {
    this.learningService.getUserCourses(userId).subscribe(courses => {
      this.assignedCourses.set(courses);
    });
  }

  // User Management
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
      email: user.email,
      role: user.role
    });
    this.form.controls.password.removeValidators(Validators.required);
    this.form.controls.password.updateValueAndValidity();
    this.showForm.set(true);
  }

  setTab(tab: 'users' | 'groups' | 'lti' | 'email') {
    this.activeTab.set(tab);
    this.searchTerm.set('');
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

  onAssign() {
    if (this.assignForm.valid) {
      const { userId, courseId } = this.assignForm.value;
      if (userId && courseId) {
        this.learningService.assignCourse(Number(userId), Number(courseId)).subscribe({
          next: () => {
            alert('Course assigned successfully');
            this.loadAssignedCourses(Number(userId));
            this.assignForm.patchValue({ courseId: '' }); // Reset course selection only
          },
          error: (err) => {
            alert(err.error?.error || 'Failed to assign course');
          }
        });
      }
    }
  }

  detachCourse(courseId: number) {
    const userId = this.assignForm.value.userId;
    if (userId && confirm('Are you sure you want to remove this course from the user?')) {
      this.learningService.detachCourse(Number(userId), courseId).subscribe({
        next: () => {
          this.loadAssignedCourses(Number(userId));
        },
        error: (err) => {
          alert(err.error?.error || 'Failed to detach course');
        }
      });
    }
  }

  // Group Management
  startCreateGroup() {
    this.isEditingGroup.set(false);
    this.currentGroupId = null;
    this.groupForm.reset();
    this.showGroupForm.set(true);
  }

  startEditGroup(group: any) {
    this.isEditingGroup.set(true);
    this.currentGroupId = group.id;
    this.groupForm.patchValue({
      name: group.name,
      description: group.description
    });
    this.showGroupForm.set(true);
  }

  cancelGroup() {
    this.showGroupForm.set(false);
    this.groupForm.reset();
  }

  onSubmitGroup() {
    if (this.groupForm.valid) {
      const group = this.groupForm.value;
      if (this.isEditingGroup() && this.currentGroupId) {
        this.groupsService.updateGroup(this.currentGroupId, group).subscribe(() => {
          this.loadGroups();
          this.cancelGroup();
        });
      } else {
        this.groupsService.createGroup(group).subscribe(() => {
          this.loadGroups();
          this.cancelGroup();
        });
      }
    }
  }

  deleteGroup(id: number) {
    if (confirm('Are you sure you want to delete this group?')) {
      this.groupsService.deleteGroup(id).subscribe(() => {
        this.loadGroups();
        if (this.selectedGroup && this.selectedGroup.id === id) {
          this.selectedGroup = null;
        }
      });
    }
  }

  selectGroup(group: any) {
    this.selectedGroup = group;
    this.loadGroupDetails(group.id);
  }

  loadGroupDetails(groupId: number) {
    this.groupsService.getGroupUsers(groupId).subscribe(users => {
      this.groupUsers.set(users);
    });
    this.groupsService.getGroupCourses(groupId).subscribe(courses => {
      this.groupCourses.set(courses);
    });
    this.apiService.getGroupMonitors(groupId).subscribe(monitors => {
      this.groupMonitors.set(monitors);
    });
  }

  addUserToGroup() {
    if (this.assignGroupUserForm.valid && this.selectedGroup) {
      const userId = this.assignGroupUserForm.value.userId;
      this.groupsService.addUserToGroup(this.selectedGroup.id, Number(userId)).subscribe({
        next: () => {
          this.loadGroupDetails(this.selectedGroup.id);
          this.assignGroupUserForm.reset();
        },
        error: (err) => {
          alert(err.error?.error || 'Failed to add user to group');
        }
      });
    }
  }

  removeUserFromGroup(userId: number) {
    if (this.selectedGroup && confirm('Remove user from group?')) {
      this.groupsService.removeUserFromGroup(this.selectedGroup.id, userId).subscribe(() => {
        this.loadGroupDetails(this.selectedGroup.id);
      });
    }
  }

  addMonitorToGroup() {
    if (this.assignGroupMonitorForm.valid && this.selectedGroup) {
      const userId = this.assignGroupMonitorForm.value.userId;
      this.apiService.addMonitorToGroup(this.selectedGroup.id, Number(userId)).subscribe({
        next: () => {
          this.loadGroupDetails(this.selectedGroup.id);
          this.assignGroupMonitorForm.reset();
        },
        error: (err) => {
          alert(err.error?.error || 'Failed to add monitor to group');
        }
      });
    }
  }

  removeMonitorFromGroup(userId: number) {
    if (this.selectedGroup && confirm('Remove monitor from group?')) {
      this.apiService.removeMonitorFromGroup(this.selectedGroup.id, userId).subscribe(() => {
        this.loadGroupDetails(this.selectedGroup.id);
      });
    }
  }

  addCourseToGroup() {
    if (this.assignGroupCourseForm.valid && this.selectedGroup) {
      const { courseId, validityDays } = this.assignGroupCourseForm.value;
      const days = validityDays ? Number(validityDays) : undefined;

      this.groupsService.addCourseToGroup(this.selectedGroup.id, Number(courseId), days).subscribe({
        next: () => {
          this.loadGroupDetails(this.selectedGroup.id);
          this.assignGroupCourseForm.reset();
        },
        error: (err) => {
          alert(err.error?.error || 'Failed to add course to group');
        }
      });
    }
  }

  removeCourseFromGroup(courseId: number) {
    if (this.selectedGroup && confirm('Remove course from group?')) {
      this.groupsService.removeCourseFromGroup(this.selectedGroup.id, courseId).subscribe(() => {
        this.loadGroupDetails(this.selectedGroup.id);
      });
    }
  }

  // Email
  sendTestEmail() {
    if (this.testEmailControl.valid) {
      this.isSendingEmail.set(true);
      this.emailStatus.set(null);

      const email = this.testEmailControl.value!;

      this.apiService.sendTestEmail(email).subscribe({
        next: (response) => {
          this.isSendingEmail.set(false);
          this.emailStatus.set({
            success: true,
            message: response.message || 'Email sent successfully!'
          });
        },
        error: (err) => {
          this.isSendingEmail.set(false);
          this.emailStatus.set({
            success: false,
            message: err.error?.message || 'Failed to send email. Check your configuration.'
          });
        }
      });
    }
  }
}
