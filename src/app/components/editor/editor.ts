import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CourseService } from '../../services/course.service';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { marked } from 'marked';

@Component({
  selector: 'app-editor',
  imports: [ReactiveFormsModule],
  templateUrl: './editor.html',
  styleUrl: './editor.css',
})
export class Editor implements OnInit {
  private courseService = inject(CourseService);
  private fb = inject(FormBuilder);

  courses = signal<any[]>([]);
  selectedCourse = signal<any>(null);

  pages = signal<any[]>([]);
  // Filter pages by selected course
  coursePages = computed(() => {
    if (!this.selectedCourse()) return [];
    return this.pages()
      .filter(p => p.course_id === this.selectedCourse().id)
      .sort((a, b) => a.display_order - b.display_order);
  });

  selectedPage = signal<any>(null);
  previewHtml = signal<string>('');

  form = this.fb.group({
    title: ['', Validators.required],
    content: [''],
    course_id: [null as number | null]
  });

  ngOnInit() {
    this.loadCourses();
    this.loadPages();

    // Update preview when content changes
    this.form.get('content')?.valueChanges.subscribe(val => {
      this.updatePreview(val || '');
    });
  }

  async updatePreview(content: string) {
    const html = await marked.parse(content);
    this.previewHtml.set(html);
  }

  loadCourses() {
    this.courseService.getCourses().subscribe(courses => {
      this.courses.set(courses);
      // Restore selection if needed
      if (this.selectedCourse()) {
        const updated = courses.find(c => c.id === this.selectedCourse().id);
        this.selectedCourse.set(updated || null);
      }
    });
  }

  loadPages() {
    this.courseService.getPages().subscribe(pages => {
      this.pages.set(pages);
      if (this.selectedPage()) {
        const updated = pages.find(p => p.id === this.selectedPage().id);
        if (updated) {
          this.selectPage(updated);
        } else {
          this.selectedPage.set(null);
        }
      }
    });
  }

  selectCourse(course: any) {
    this.selectedCourse.set(course);
    this.selectedPage.set(null);
  }

  createCourse() {
    const title = prompt('Enter course title:');
    if (title) {
      this.courseService.createCourse({ title }).subscribe(() => {
        this.loadCourses();
      });
    }
  }

  deleteCourse(course: any) {
    if (confirm(`Delete course "${course.title}" and all its pages?`)) {
      this.courseService.deleteCourse(course.id).subscribe(() => {
        this.selectedCourse.set(null);
        this.loadCourses();
        this.loadPages(); // Pages might be deleted by cascade or need refresh
      });
    }
  }

  selectPage(page: any) {
    this.selectedPage.set(page);
    this.form.patchValue({
      title: page.title,
      content: page.content,
      course_id: page.course_id
    });
    this.updatePreview(page.content || '');
  }

  createPage() {
    if (!this.selectedCourse()) return;
    const newPage = {
      title: 'New Page',
      content: '',
      course_id: this.selectedCourse().id
    };
    this.courseService.createPage(newPage).subscribe(() => {
      this.loadPages();
    });
  }

  savePage() {
    if (this.selectedPage() && this.form.valid) {
      const updatedPage = { ...this.form.value, course_id: this.selectedCourse().id };
      this.courseService.updatePage(this.selectedPage().id, updatedPage).subscribe(() => {
        this.loadPages();
        alert('Saved!');
      });
    }
  }

  deletePage() {
    if (this.selectedPage() && confirm('Are you sure?')) {
      this.courseService.deletePage(this.selectedPage().id).subscribe(() => {
        this.selectedPage.set(null);
        this.loadPages();
      });
    }
  }

  movePage(page: any, direction: 'up' | 'down') {
    const pages = this.coursePages();
    const index = pages.findIndex(p => p.id === page.id);
    if (index === -1) return;

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= pages.length) return;

    const otherPage = pages[newIndex];

    // Swap display_order
    // Ideally this should be done on backend or by swapping order values
    // For simplicity, let's assume display_order is index based or we just swap them
    // We need to update both pages

    // Assign temp orders
    const pageOrder = page.display_order || 0;
    const otherOrder = otherPage.display_order || 0;

    // If orders are same (e.g. 0), we need to fix them first. 
    // But let's just swap their current positions in the array and assign new orders based on index

    // Better approach: re-assign order for all pages in this course based on current array + swap
    const reordered = [...pages];
    [reordered[index], reordered[newIndex]] = [reordered[newIndex], reordered[index]];

    // Update all pages with new order
    reordered.forEach((p, i) => {
      if (p.display_order !== i) {
        this.courseService.updatePage(p.id, { ...p, display_order: i }).subscribe();
      }
    });

    // Optimistic update
    this.loadPages();
  }
}
