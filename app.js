// глоб переменные
let selectedDevices = [] // создание
let currentEditUserId = null // редактироввание

// модалки
function openModal(id) {
    const modal = document.getElementById(id)
    if (modal) {
        modal.classList.add('active')
        
        if (id === 'modal-create-user') {
            selectedDevices = []
            renderCreateUserDevices()
        }
    }
}

function closeModal(id) {
    const modal = document.getElementById(id)
    if (modal) modal.classList.remove('active')
}

function closeModalOutside(event, id) {
    if (event.target === event.currentTarget) {
        closeModal(id)
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'))
    }
})

// поиск
function searchOnEnter(event, formId) {
    if (event.key === 'Enter') {
        event.preventDefault()
        const form = document.getElementById(formId)
        if (form) form.submit()
    }
}

// фильтр устрв в выборе
function filterDeviceSelect(query) {
    const items = document.querySelectorAll('.device-select-item')
    const q = query.toLowerCase()
    items.forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q) ? 'flex' : 'none'
    })
}

// модалка устр
function openAddDeviceModal(userId) {
    currentEditUserId = userId
    const container = document.getElementById('device-select-container')
    if (!container) {
        console.error('Container #device-select-container not found')
        return
    }
    
    container.innerHTML = '<p style="padding:20px;text-align:center;color:#999;">Загрузка...</p>'
    openModal('modal-add-device')

    fetch('api/get_available_devices.php')
        .then(r => r.json())
        .then(devices => {
            let html = '<div class="device-select-list">'
            devices.forEach(d => {
                html += `
                    <label class="device-select-item">
                        <input type="checkbox" value="${d.id}" 
                               data-code="${d.inventory_code}" 
                               data-type="${d.device_type}">
                        <div class="ds-info">
                            <div class="ds-code">${d.inventory_code}</div>
                            <div class="ds-type">${d.device_type}</div>
                            <div class="ds-desc">${d.description || '-'}</div>
                        </div>
                    </label>`
            })
            html += '</div>'
            container.innerHTML = html
        })
        .catch(err => {
            console.error('Error loading devices:', err)
            container.innerHTML = '<p style="padding:20px;color:red;">Ошибка загрузки</p>'
        })
}

// добавление устройств
function addSelectedDevices() {
    const checkboxes = document.querySelectorAll('#device-select-container input[type="checkbox"]:checked')
    
    const newDevices = Array.from(checkboxes).map(cb => ({
        id: cb.value,
        code: cb.dataset.code,
        type: cb.dataset.type
    }))
    
    if (currentEditUserId > 0) {
        // редактирование
        const container = document.getElementById(`device-tags-${currentEditUserId}`)
        const hiddenInput = document.getElementById(`devices-${currentEditUserId}`)
        
        if (container && hiddenInput) {
            container.innerHTML = newDevices.map(d => 
                `<span class="device-tag device-tag-lock">${d.code} - ${d.type}</span>`
            ).join('')
            
            hiddenInput.value = newDevices.map(d => d.id).join(',')
        }
        
        closeModal('modal-add-device')
        
    } else {
        // создание
        selectedDevices = newDevices
        renderCreateUserDevices()
        closeModal('modal-add-device')
    }
}

// добавленные устройства в создании
function renderCreateUserDevices() {
    const container = document.querySelector('#modal-create-user .device-tags')
    const hiddenInput = document.querySelector('#modal-create-user input[name="devices"]')
    
    if (!container || !hiddenInput) return
    
    if (selectedDevices.length === 0) {
        container.innerHTML = '<span style="color:#999;font-size:13px;">Устройства не выбраны</span>'
        hiddenInput.value = ''
    } else {
        container.innerHTML = selectedDevices.map(d => 
            `<span class="device-tag">${d.code} - ${d.type} <span class="remove-device" onclick="removeFromSelected(${d.id})">&times;</span></span>`
        ).join('')
        hiddenInput.value = selectedDevices.map(d => d.id).join(',')
    }
}

// удаление из выбранных устр
function removeFromSelected(id) {
    selectedDevices = selectedDevices.filter(d => d.id != id)
    renderCreateUserDevices()
}

// вкладки
function switchTab(tabGroup, tabName) {
    const group = document.querySelector(`[data-tabs="${tabGroup}"]`)
    if (!group) return
    group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'))
    group.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none')
    const activeTab = group.querySelector(`.tab[data-tab="${tabName}"]`)
    if (activeTab) activeTab.classList.add('active')
    const content = group.querySelector(`.tab-content[data-tab="${tabName}"]`)
    if (content) content.style.display = 'block'
}

// фильтр заявок
function filterTickets(tableId, query) {
    const table = document.getElementById(tableId)
    if (!table) return
    const q = query.toLowerCase()
    table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none'
    })
}

// загрузка устр пользователя при ред и уд
function refreshUserDevices(userId) {
    const container = document.getElementById(`device-tags-${userId}`)
    const hiddenInput = document.getElementById(`devices-${userId}`)
    
    if (!container) return
    
    fetch(`api/get_user_devices.php?user_id=${userId}`)
        .then(r => r.json())
        .then(devices => {
            if (devices.length === 0) {
                container.innerHTML = '<span style="color:#999; font-size:13px;">Устройства не выбраны</span>'
                if (hiddenInput) hiddenInput.value = ''
            } else {
                container.innerHTML = devices.map(d => 
                    `<span class="device-tag" style="cursor:pointer;">
                        ${d.inventory_code} - ${d.device_type}
                        <span class="remove-device" onclick="unassignDevice(${d.id}, ${userId})">&times;</span>
                    </span>`
                ).join('')
                
                if (hiddenInput) {
                    hiddenInput.value = devices.map(d => d.id).join(',')
                }
            }
        })
        .catch(err => console.error('Error loading devices:', err))
}

// возврат устр
function unassignDevice(deviceId, userId) {
    customConfirm('Вернуть устройство на склад?', () => {
        fetch('api/unassign_device.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ device_id: deviceId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                refreshUserDevices(userId)
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось отвязать устройство'))
            }
        })
        .catch(() => alert('Ошибка сети'))
    })
}

// показ подтверждения
let confirmCallback = null

function customConfirm(message, callback) {
    document.getElementById('confirm-text').innerText = message
    confirmCallback = callback
    openModal('modal-confirm')
}

// обработчик кнопки да в модалке
document.getElementById('confirm-ok').onclick = function () {
    closeModal('modal-confirm')
    if (confirmCallback) confirmCallback()
}

// удаление пользователей
function deleteUser(userId) {
    customConfirm('Вернуть устройства на склад и удалить пользователя?', () => {
        fetch('api/delete_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ user_id: userId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('modal-confirm')
                closeModal('modal-info-about-' + userId)
                location.reload()
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось удалить пользователя'))
            }
        })
        .catch(() => alert('Ошибка сети'))
    })
}