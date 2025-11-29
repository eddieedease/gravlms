import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TestViewer } from './test-viewer';

describe('TestViewer', () => {
  let component: TestViewer;
  let fixture: ComponentFixture<TestViewer>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TestViewer]
    })
    .compileComponents();

    fixture = TestBed.createComponent(TestViewer);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
